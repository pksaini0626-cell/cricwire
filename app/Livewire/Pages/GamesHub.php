<?php

namespace App\Livewire\Pages;

use App\Models\Friendship;
use App\Models\SquadBattle;
use App\Models\User;
use App\Services\CricGemsService;
use App\Services\SquadBattleService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class GamesHub extends Component
{
    public string $matchFilter = 'today';
    public ?int $selectedOpponentId = null;
    public ?string $selectedMatchId = null;
    public int $selectedTeamSize = 5;

    public string $captainUsernameInput = '';
    public bool $editingUsername = false;
    public ?string $usernameMessage = null;
    public ?string $usernameError = null;

    public ?string $challengeError = null;
    public ?string $challengeSuccess = null;

    public function mount(CricGemsService $cricGemsService): void
    {
        if (Auth::check()) {
            $user = Auth::user();
            $cricGemsService->syncOnAuthenticatedVisit($user);
            $this->captainUsernameInput = (string) ($user->captain_username ?? '');
        }
    }

    public function setMatchFilter(string $filter): void
    {
        $this->matchFilter = $filter === 'top10' ? 'top10' : 'today';
        $this->selectedMatchId = null;
    }

    public function selectOpponent(int $opponentId): void
    {
        $this->selectedOpponentId = $opponentId;
        $this->challengeError = null;
    }

    public function selectMatch(string $matchId): void
    {
        $this->selectedMatchId = $matchId;
        $this->challengeError = null;
    }

    public function selectTeamSize(int $size): void
    {
        $this->selectedTeamSize = $size === 3 ? 3 : 5;
    }

    public function toggleUsernameEditor(): void
    {
        $this->editingUsername = !$this->editingUsername;
        $this->usernameMessage = null;
        $this->usernameError = null;
        if ($this->editingUsername && Auth::check()) {
            $this->captainUsernameInput = (string) (Auth::user()->captain_username ?? '');
        }
    }

    public function saveCaptainUsername(): void
    {
        $this->usernameMessage = null;
        $this->usernameError = null;

        $user = Auth::user();
        if (!$user) {
            return;
        }

        $cleaned = trim($this->captainUsernameInput);
        if (!preg_match('/^[A-Za-z0-9_]{3,24}$/', $cleaned)) {
            $this->usernameError = 'Captain Username must be 3–24 characters using letters, numbers, and underscores only.';
            return;
        }

        $exists = User::whereRaw('LOWER(captain_username) = ?', [strtolower($cleaned)])
            ->where('id', '!=', $user->id)
            ->exists();

        if ($exists) {
            $this->usernameError = 'This Captain Username is already taken. Please pick another.';
            return;
        }

        $user->update(['captain_username' => $cleaned]);
        $this->editingUsername = false;
        $this->usernameMessage = "Captain Username updated to {$cleaned}!";
    }

    public function sendChallenge(SquadBattleService $battleService)
    {
        $this->challengeError = null;
        $this->challengeSuccess = null;

        $user = Auth::user();
        if (!$user) {
            $this->challengeError = 'Please sign in with Google to challenge a Captain.';
            return null;
        }

        if (!$this->selectedOpponentId) {
            $this->challengeError = 'Step 1: Please select a friend to challenge.';
            return null;
        }

        if (!$this->selectedMatchId) {
            $this->challengeError = 'Step 2: Please select an upcoming T20 or ODI match.';
            return null;
        }

        $opponent = User::find($this->selectedOpponentId);
        if (!$opponent) {
            $this->challengeError = 'Selected opponent was not found.';
            return null;
        }

        $matches = $battleService->getEligibleUpcomingMatches($this->matchFilter);
        $selectedMatch = collect($matches)->firstWhere('match_id', (string) $this->selectedMatchId);
        if (!$selectedMatch && $this->matchFilter !== 'top10') {
            $selectedMatch = collect($battleService->getEligibleUpcomingMatches('top10'))->firstWhere('match_id', (string) $this->selectedMatchId);
        }

        if (!$selectedMatch) {
            $this->challengeError = 'Selected match is no longer eligible or has already reached its 5-minute pre-match lock.';
            return null;
        }

        try {
            $battle = $battleService->createChallenge($user, $opponent, $selectedMatch, $this->selectedTeamSize);
            return $this->redirect(route('games.battle.show', $battle->slug), navigate: true);
        } catch (\Throwable $e) {
            $this->challengeError = $e->getMessage();
            return null;
        }
    }

    public function acceptChallenge(int $battleId, SquadBattleService $battleService)
    {
        $this->challengeError = null;
        $user = Auth::user();
        if (!$user) {
            return null;
        }

        $battle = SquadBattle::find($battleId);
        if (!$battle) {
            $this->challengeError = 'Battle could not be found.';
            return null;
        }

        try {
            $accepted = $battleService->acceptChallenge($battle, $user);
            return $this->redirect(route('games.battle.show', $accepted->slug), navigate: true);
        } catch (\Throwable $e) {
            $this->challengeError = $e->getMessage();
            return null;
        }
    }

    public function declineChallenge(int $battleId, SquadBattleService $battleService): void
    {
        $this->challengeError = null;
        $user = Auth::user();
        if (!$user) {
            return;
        }

        $battle = SquadBattle::find($battleId);
        if (!$battle) {
            return;
        }

        try {
            $battleService->declineChallenge($battle, $user);
            $this->challengeSuccess = 'Challenge declined.';
        } catch (\Throwable $e) {
            $this->challengeError = $e->getMessage();
        }
    }

    public function render(SquadBattleService $battleService)
    {
        $currentUser = Auth::user()?->fresh();

        $friends = collect();
        $incomingChallenges = collect();
        $myActiveBattles = collect();
        $myPastBattles = collect();

        if ($currentUser) {
            $acceptedFriendships = Friendship::with(['sender', 'receiver'])
                ->where('status', Friendship::STATUS_ACCEPTED)
                ->where(function ($q) use ($currentUser) {
                    $q->where('sender_id', $currentUser->id)
                        ->orWhere('receiver_id', $currentUser->id);
                })
                ->latest('accepted_at')
                ->get();

            $friends = $acceptedFriendships->map(function (Friendship $f) use ($currentUser) {
                return (int) $f->sender_id === (int) $currentUser->id ? $f->receiver : $f->sender;
            })->filter()->values();

            $userBattles = SquadBattle::with(['challenger', 'opponent', 'winner'])
                ->where(function ($q) use ($currentUser) {
                    $q->where('challenger_id', $currentUser->id)
                        ->orWhere('opponent_id', $currentUser->id);
                })
                ->latest('id')
                ->limit(30)
                ->get();

            // Expire any stale pending/drafting battles cleanly
            foreach ($userBattles as $ub) {
                if (in_array($ub->status, [SquadBattle::STATUS_PENDING_ACCEPT, SquadBattle::STATUS_DRAFTING], true) && !$ub->isBeforeLock()) {
                    $battleService->expireIfIncomplete($ub);
                    $ub->refresh();
                }
            }

            $incomingChallenges = $userBattles->filter(function (SquadBattle $b) use ($currentUser) {
                return (int) $b->opponent_id === (int) $currentUser->id
                    && $b->status === SquadBattle::STATUS_PENDING_ACCEPT
                    && $b->isBeforeLock();
            })->values();

            $myActiveBattles = $userBattles->filter(function (SquadBattle $b) use ($currentUser) {
                if ($b->status === SquadBattle::STATUS_PENDING_ACCEPT && (int) $b->challenger_id === (int) $currentUser->id) {
                    return true;
                }
                return in_array($b->status, [SquadBattle::STATUS_DRAFTING, SquadBattle::STATUS_LOCKED_LIVE], true);
            })->values();

            $myPastBattles = $userBattles->filter(function (SquadBattle $b) {
                return in_array($b->status, [
                    SquadBattle::STATUS_SETTLED,
                    SquadBattle::STATUS_DECLINED,
                    SquadBattle::STATUS_EXPIRED_INCOMPLETE,
                ], true);
            })->values();
        }

        $eligibleMatches = $battleService->getEligibleUpcomingMatches($this->matchFilter);

        $publicBattles = SquadBattle::with(['challenger', 'opponent', 'winner'])
            ->whereIn('status', [SquadBattle::STATUS_LOCKED_LIVE, SquadBattle::STATUS_SETTLED])
            ->latest('id')
            ->limit(12)
            ->get();

        $seoData = [
            'title' => 'Cricket Games Hub — Captain vs Captain Squad Runs Battle | Cricwire',
            'description' => 'Challenge friends to 5v5 or 3v3 Captain vs Captain Squad Runs Battles on T20 & ODI cricket matches. Draft live, swap benched players, and win CricGems (1 Run = 1 CricGem)!',
            'canonical' => route('games.index'),
        ];

        return view('livewire.pages.games-hub', [
            'user' => $currentUser,
            'friends' => $friends,
            'eligibleMatches' => $eligibleMatches,
            'incomingChallenges' => $incomingChallenges,
            'myActiveBattles' => $myActiveBattles,
            'myPastBattles' => $myPastBattles,
            'publicBattles' => $publicBattles,
        ])->layout('layouts.app', [
            'seoData' => $seoData,
            'title' => $seoData['title'],
        ]);
    }
}
