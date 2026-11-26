<?php

namespace App\Services;

use App\Models\CricGemTransaction;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class CricGemsService
{
    public const JOINING_BONUS_AMOUNT = 500;
    public const REFERRAL_BONUS_AMOUNT = 500;
    public const MAX_REFERRALS_PER_USER = 50;
    public const MAX_DAILY_STREAK_REWARD = 25;

    /**
     * Ensure the user has a unique CW-XXXXXX player code.
     */
    public function ensurePlayerCode(User $user): string
    {
        if (!empty($user->player_code)) {
            return $user->player_code;
        }

        do {
            $code = 'CW-' . random_int(100000, 999999);
            $exists = User::where('player_code', $code)->exists();
        } while ($exists);

        $user->forceFill(['player_code' => $code])->save();

        return $code;
    }

    /**
     * Atomically credit CricGems to a user and record a double-entry transaction.
     */
    public function credit(
        User $user,
        int $amount,
        string $type,
        string $description,
        ?string $refType = null,
        ?int $refId = null
    ): CricGemTransaction {
        if ($amount <= 0) {
            throw new InvalidArgumentException('Credit amount must be greater than zero.');
        }

        return DB::transaction(function () use ($user, $amount, $type, $description, $refType, $refId) {
            $lockedUser = User::where('id', $user->id)->lockForUpdate()->firstOrFail();

            $newBalance = (int) $lockedUser->cric_gems + $amount;
            $lockedUser->cric_gems = $newBalance;
            $lockedUser->save();

            $transaction = CricGemTransaction::create([
                'user_id' => $lockedUser->id,
                'type' => $type,
                'amount' => $amount,
                'balance_after' => $newBalance,
                'description' => $description,
                'reference_type' => $refType,
                'reference_id' => $refId,
            ]);

            $user->cric_gems = $newBalance;

            return $transaction;
        });
    }

    /**
     * Atomically debit CricGems from a user and record a double-entry transaction.
     * When $allowNegative is true (used for post-match Squad Battle settlement), balance can go below zero.
     */
    public function debit(
        User $user,
        int $amount,
        string $type,
        string $description,
        ?string $refType = null,
        ?int $refId = null,
        bool $allowNegative = false
    ): CricGemTransaction {
        if ($amount <= 0) {
            throw new InvalidArgumentException('Debit amount must be greater than zero.');
        }

        return DB::transaction(function () use ($user, $amount, $type, $description, $refType, $refId, $allowNegative) {
            $lockedUser = User::where('id', $user->id)->lockForUpdate()->firstOrFail();

            if (!$allowNegative && (int) $lockedUser->cric_gems < $amount) {
                throw new RuntimeException('Insufficient CricGems balance.');
            }

            $newBalance = (int) $lockedUser->cric_gems - $amount;
            $lockedUser->cric_gems = $newBalance;
            $lockedUser->save();

            $transaction = CricGemTransaction::create([
                'user_id' => $lockedUser->id,
                'type' => $type,
                'amount' => -$amount,
                'balance_after' => $newBalance,
                'description' => $description,
                'reference_type' => $refType,
                'reference_id' => $refId,
            ]);

            $user->cric_gems = $newBalance;

            return $transaction;
        });
    }

    /**
     * Grant the one-time +500 CricGems Welcome Joining Bonus if never granted.
     */
    public function grantJoiningBonusIfEligible(User $user): void
    {
        DB::transaction(function () use ($user) {
            $lockedUser = User::where('id', $user->id)->lockForUpdate()->first();
            if (!$lockedUser) {
                return;
            }

            $alreadyGranted = CricGemTransaction::where('user_id', $lockedUser->id)
                ->where('type', 'joining_bonus')
                ->exists();

            if ($alreadyGranted) {
                return;
            }

            $newBalance = (int) $lockedUser->cric_gems + self::JOINING_BONUS_AMOUNT;
            $lockedUser->cric_gems = $newBalance;
            $lockedUser->save();

            CricGemTransaction::create([
                'user_id' => $lockedUser->id,
                'type' => 'joining_bonus',
                'amount' => self::JOINING_BONUS_AMOUNT,
                'balance_after' => $newBalance,
                'description' => 'Welcome Joining Bonus (+500 CricGems)',
            ]);

            $user->cric_gems = $newBalance;
        });
    }

    /**
     * Process the IST daily login streak and credit daily loyalty points.
     *
     * @return array{streak: int, reward: int}|null
     */
    public function processDailyStreak(User $user): ?array
    {
        $nowIst = Carbon::now('Asia/Kolkata');
        $today = $nowIst->toDateString();
        $yesterday = $nowIst->copy()->subDay()->toDateString();

        $result = DB::transaction(function () use ($user, $today, $yesterday) {
            $lockedUser = User::where('id', $user->id)->lockForUpdate()->first();
            if (!$lockedUser) {
                return null;
            }

            $lastDate = $this->normalizeDateString($lockedUser->getRawOriginal('last_streak_date') ?? $lockedUser->last_streak_date);

            if ($lastDate === $today) {
                return null;
            }

            $newStreak = ($lastDate === $yesterday)
                ? ((int) $lockedUser->login_streak + 1)
                : 1;

            $reward = min(self::MAX_DAILY_STREAK_REWARD, $newStreak * 5);
            $newBalance = (int) $lockedUser->cric_gems + $reward;

            $lockedUser->login_streak = $newStreak;
            $lockedUser->last_streak_date = $today;
            $lockedUser->cric_gems = $newBalance;
            $lockedUser->save();

            CricGemTransaction::create([
                'user_id' => $lockedUser->id,
                'type' => 'daily_streak',
                'amount' => $reward,
                'balance_after' => $newBalance,
                'description' => "Day {$newStreak} Daily Login Streak Bonus",
            ]);

            $user->login_streak = $newStreak;
            $user->last_streak_date = $today;
            $user->cric_gems = $newBalance;

            return [
                'streak' => $newStreak,
                'reward' => $reward,
                'locked_user' => $lockedUser,
            ];
        });

        if ($result === null) {
            return null;
        }

        // Check if completing this streak unlocks the pending referral bonus for the referrer
        $this->grantReferralBonusWhenQualified($result['locked_user']);

        return [
            'streak' => $result['streak'],
            'reward' => $result['reward'],
        ];
    }

    /**
     * Attach a referrer to a newly registered user (within 15 minutes of signup).
     */
    public function applyReferralCode(User $newUser, string $refCode): void
    {
        $normalized = strtoupper(trim($refCode));
        if ($normalized === '') {
            return;
        }

        if ($newUser->referred_by_user_id !== null) {
            return;
        }

        if (!$newUser->created_at || $newUser->created_at->lt(now()->subMinutes(15))) {
            return;
        }

        $referrer = User::where('player_code', $normalized)
            ->where('id', '!=', $newUser->id)
            ->first();

        if (!$referrer) {
            return;
        }

        $totalReferrals = User::where('referred_by_user_id', $referrer->id)->count();
        if ($totalReferrals >= self::MAX_REFERRALS_PER_USER) {
            return;
        }

        DB::transaction(function () use ($newUser, $referrer) {
            $lockedNewUser = User::where('id', $newUser->id)->lockForUpdate()->first();
            if (!$lockedNewUser || $lockedNewUser->referred_by_user_id !== null) {
                return;
            }

            $lockedNewUser->referred_by_user_id = $referrer->id;
            $lockedNewUser->save();

            $newUser->referred_by_user_id = $referrer->id;
        });

        $this->grantReferralBonusWhenQualified($newUser);
    }

    /**
     * Unlock the +500 CricGems referral bonus once the invited user completes
     * their 2nd daily login streak OR their 1st battle.
     */
    public function grantReferralBonusWhenQualified(User $user): void
    {
        if ($user->referred_by_user_id === null || $user->referral_rewarded) {
            return;
        }

        $isQualified = (int) $user->login_streak >= 2 || (int) $user->battles_played >= 1;
        if (!$isQualified) {
            return;
        }

        DB::transaction(function () use ($user) {
            $lockedUser = User::where('id', $user->id)->lockForUpdate()->first();
            if (
                !$lockedUser ||
                $lockedUser->referred_by_user_id === null ||
                $lockedUser->referral_rewarded
            ) {
                return;
            }

            if ((int) $lockedUser->login_streak < 2 && (int) $lockedUser->battles_played < 1) {
                return;
            }

            $referrer = User::where('id', $lockedUser->referred_by_user_id)->lockForUpdate()->first();
            if (!$referrer || $referrer->id === $lockedUser->id) {
                return;
            }

            $rewardedCount = User::where('referred_by_user_id', $referrer->id)
                ->where('referral_rewarded', true)
                ->count();

            if ($rewardedCount >= self::MAX_REFERRALS_PER_USER) {
                return;
            }

            $lockedUser->referral_rewarded = true;
            $lockedUser->save();
            $user->referral_rewarded = true;

            $newBalance = (int) $referrer->cric_gems + self::REFERRAL_BONUS_AMOUNT;
            $referrer->cric_gems = $newBalance;
            $referrer->save();

            CricGemTransaction::create([
                'user_id' => $referrer->id,
                'type' => 'referral_bonus',
                'amount' => self::REFERRAL_BONUS_AMOUNT,
                'balance_after' => $newBalance,
                'description' => "Referral Bonus: {$lockedUser->name} joined & activated",
                'reference_type' => User::class,
                'reference_id' => $lockedUser->id,
            ]);
        });
    }

    /**
     * Throttled helper for authenticated page loads (/more and /).
     * Runs at most once per IST day per session.
     *
     * @return array{streak: int, reward: int}|null
     */
    public function syncOnAuthenticatedVisit(User $user): ?array
    {
        $todayIst = Carbon::now('Asia/Kolkata')->toDateString();

        if (
            session('streak_checked_ist_date') === $todayIst &&
            (int) session('streak_checked_user_id') === (int) $user->id &&
            !empty($user->player_code)
        ) {
            return null;
        }

        $this->ensurePlayerCode($user);
        $this->grantJoiningBonusIfEligible($user);
        $streakResult = $this->processDailyStreak($user);

        session([
            'streak_checked_ist_date' => $todayIst,
            'streak_checked_user_id' => $user->id,
        ]);

        if ($streakResult !== null) {
            session()->flash('cricgems_streak_toast', $streakResult);
        }

        return $streakResult;
    }

    protected function normalizeDateString(mixed $value): ?string
    {
        if ($value instanceof CarbonInterface) {
            return $value->toDateString();
        }

        if (is_string($value) && trim($value) !== '') {
            return substr(trim($value), 0, 10);
        }

        return null;
    }
}
