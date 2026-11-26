<?php

namespace App\Livewire\Pages;

use App\Models\Battle;
use App\Models\CricGemTransaction;
use App\Models\Friendship;
use App\Models\User;
use App\Services\CricGemsService;
use App\Support\LiveEngineConfig;
use App\Support\QrCodeSvg;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Session;
use Livewire\Component;

class MorePage extends Component
{
    public bool $pushNotifications = true;
    public string $friendSearch = '';
    public bool $showPassbook = false;
    public ?string $addFriendCode = null;
    public ?string $friendActionMessage = null;
    public ?string $friendActionError = null;
    public ?int $confirmRemoveFriendId = null;
    public ?array $streakToast = null;

    public function mount(CricGemsService $cricGemsService): void
    {
        $addFriend = request()->query('add_friend');
        if (is_string($addFriend) && trim($addFriend) !== '') {
            $clean = strtoupper(trim($addFriend));
            if (preg_match('/^[A-Z0-9-]{3,16}$/', $clean)) {
                $this->addFriendCode = $clean;
            }
        }

        if (Auth::check()) {
            $user = Auth::user();
            $toast = $cricGemsService->syncOnAuthenticatedVisit($user);
            if ($toast !== null) {
                $this->streakToast = $toast;
            }
        }
    }

    public function togglePassbook(): void
    {
        $this->showPassbook = !$this->showPassbook;
    }

    public function dismissAddFriendModal(): void
    {
        $this->addFriendCode = null;
    }

    public function sendFriendRequest(int $targetUserId): void
    {
        $this->friendActionMessage = null;
        $this->friendActionError = null;

        $currentUserId = Auth::id();
        if (!$currentUserId) {
            $this->friendActionError = 'Please sign in to connect with friends.';
            return;
        }

        if ($targetUserId === $currentUserId) {
            $this->friendActionError = 'You cannot send a friend request to yourself.';
            return;
        }

        $targetUser = User::find($targetUserId);
        if (!$targetUser) {
            $this->friendActionError = 'Player not found.';
            return;
        }

        $existing = Friendship::betweenUsers($currentUserId, $targetUserId)->first();

        if ($existing) {
            if ($existing->status === Friendship::STATUS_ACCEPTED) {
                $this->friendActionMessage = "You are already friends with {$targetUser->name}.";
                $this->addFriendCode = null;
                return;
            }

            if ($existing->status === Friendship::STATUS_PENDING) {
                if ((int) $existing->sender_id === $currentUserId) {
                    $this->friendActionMessage = "Friend request already sent to {$targetUser->name}.";
                    $this->addFriendCode = null;
                    return;
                }

                // Target user had already sent a request to us -> accept immediately
                $existing->update([
                    'status' => Friendship::STATUS_ACCEPTED,
                    'accepted_at' => now(),
                ]);
                $this->friendActionMessage = "You and {$targetUser->name} are now friends!";
                $this->addFriendCode = null;
                return;
            }

            // Re-send after previous decline
            $existing->update([
                'sender_id' => $currentUserId,
                'receiver_id' => $targetUserId,
                'status' => Friendship::STATUS_PENDING,
                'accepted_at' => null,
            ]);
            $this->friendActionMessage = "Friend request sent to {$targetUser->name}!";
            $this->addFriendCode = null;
            return;
        }

        Friendship::create([
            'sender_id' => $currentUserId,
            'receiver_id' => $targetUserId,
            'status' => Friendship::STATUS_PENDING,
        ]);

        $this->friendActionMessage = "Friend request sent to {$targetUser->name}!";
        $this->addFriendCode = null;
    }

    public function acceptFriendRequest(int $friendshipId): void
    {
        $this->friendActionMessage = null;
        $this->friendActionError = null;

        $currentUserId = Auth::id();
        if (!$currentUserId) {
            return;
        }

        $friendship = Friendship::with('sender')
            ->where('id', $friendshipId)
            ->where('receiver_id', $currentUserId)
            ->where('status', Friendship::STATUS_PENDING)
            ->first();

        if (!$friendship) {
            $this->friendActionError = 'Friend request could not be found.';
            return;
        }

        $friendship->update([
            'status' => Friendship::STATUS_ACCEPTED,
            'accepted_at' => now(),
        ]);

        $senderName = $friendship->sender?->name ?? 'Player';
        $this->friendActionMessage = "You and {$senderName} are now friends!";
    }

    public function declineFriendRequest(int $friendshipId): void
    {
        $this->friendActionMessage = null;
        $this->friendActionError = null;

        $currentUserId = Auth::id();
        if (!$currentUserId) {
            return;
        }

        $friendship = Friendship::where('id', $friendshipId)
            ->where('receiver_id', $currentUserId)
            ->where('status', Friendship::STATUS_PENDING)
            ->first();

        if (!$friendship) {
            return;
        }

        $friendship->update(['status' => Friendship::STATUS_DECLINED]);
        $this->friendActionMessage = 'Friend request declined.';
    }

    public function confirmRemoveFriend(int $friendUserId): void
    {
        $this->confirmRemoveFriendId = $friendUserId;
    }

    public function cancelRemoveFriend(): void
    {
        $this->confirmRemoveFriendId = null;
    }

    public function removeFriend(int $friendUserId): void
    {
        $this->friendActionMessage = null;
        $this->friendActionError = null;

        $currentUserId = Auth::id();
        if (!$currentUserId) {
            return;
        }

        Friendship::betweenUsers($currentUserId, $friendUserId)
            ->where('status', Friendship::STATUS_ACCEPTED)
            ->delete();

        $this->confirmRemoveFriendId = null;
        $this->friendActionMessage = 'Friend removed from your network.';
    }

    /**
     * Log out the current user, invalidate session, and redirect to home.
     */
    public function logout()
    {
        Auth::logout();
        Session::invalidate();
        Session::regenerateToken();

        return $this->redirect('/', navigate: true);
    }

    /**
     * Delete user account, invalidate session, clear cookies, and redirect to home.
     */
    public function deleteAccount(): void
    {
        $userId = Auth::id();

        if ($userId) {
            // Delete user record from database (cascades cric_gem_transactions & friendships)
            User::where('id', $userId)->delete();

            // Log out and flush session
            Auth::logout();
            Session::invalidate();
            Session::regenerateToken();

            // Clear authentication cookies
            Cookie::queue(Cookie::forget(Auth::getRecallerName()));
            Cookie::queue(Cookie::forget('remember_token'));

            session()->flash('success', 'Your account has been permanently deleted.');
        }

        $this->redirect('/', navigate: true);
    }

    public function render()
    {
        $socialLinks = LiveEngineConfig::getActiveSocialLinks();
        $appVersion = config('app.version', '1.0.4 - beta');
        $currentUser = Auth::user()?->fresh();

        $winRate = 0;
        $inviteUrl = '';
        $qrSvg = '';
        $transactions = collect();
        $incomingRequests = collect();
        $friendsList = collect();
        $searchResults = collect();
        $promptedPlayer = null;
        $promptedRelation = null;

        if ($currentUser) {
            $played = (int) $currentUser->battles_played;
            $won = (int) $currentUser->battles_won;
            $winRate = $played > 0 ? (int) round(($won / $played) * 100) : 0;

            $inviteUrl = url('/more?add_friend=' . $currentUser->player_code . '&ref=' . $currentUser->player_code);
            $qrSvg = QrCodeSvg::render($inviteUrl, 196, '#020617', '#ffffff');

            $transactions = CricGemTransaction::where('user_id', $currentUser->id)
                ->latest('id')
                ->limit(15)
                ->get();

            $incomingRequests = Friendship::with('sender')
                ->where('receiver_id', $currentUser->id)
                ->where('status', Friendship::STATUS_PENDING)
                ->latest('id')
                ->get();

            $acceptedFriendships = Friendship::with(['sender', 'receiver'])
                ->where('status', Friendship::STATUS_ACCEPTED)
                ->where(function ($q) use ($currentUser) {
                    $q->where('sender_id', $currentUser->id)
                        ->orWhere('receiver_id', $currentUser->id);
                })
                ->latest('accepted_at')
                ->get();

            $friendUsers = $acceptedFriendships->map(function (Friendship $f) use ($currentUser) {
                return (int) $f->sender_id === (int) $currentUser->id ? $f->receiver : $f->sender;
            })->filter()->values();

            $friendIds = $friendUsers->pluck('id')->all();
            $h2hStats = $this->computeHeadToHeadStats($currentUser->id, $friendIds);

            $friendsList = $friendUsers->map(function (User $friend) use ($h2hStats) {
                $stats = $h2hStats[$friend->id] ?? ['me' => 0, 'them' => 0];
                return [
                    'user' => $friend,
                    'my_wins' => $stats['me'],
                    'friend_wins' => $stats['them'],
                ];
            });

            // Friend Search
            $term = trim($this->friendSearch);
            if (mb_strlen($term) >= 2) {
                $upperTerm = strtoupper($term);
                $matches = User::where('id', '!=', $currentUser->id)
                    ->where(function ($q) use ($term, $upperTerm) {
                        $q->where('player_code', $upperTerm)
                            ->orWhere('email', $term)
                            ->orWhere('name', 'like', '%' . $term . '%');
                    })
                    ->limit(5)
                    ->get();

                $searchResults = $matches->map(function (User $candidate) use ($currentUser) {
                    return [
                        'user' => $candidate,
                        'relation' => $this->resolveRelationState($currentUser->id, $candidate->id),
                    ];
                });
            }

            // QR / Deep-link ?add_friend=CW-XXXXXX prompt
            if ($this->addFriendCode && $this->addFriendCode !== strtoupper((string) $currentUser->player_code)) {
                $promptedPlayer = User::where('player_code', $this->addFriendCode)->first();
                if ($promptedPlayer) {
                    $promptedRelation = $this->resolveRelationState($currentUser->id, $promptedPlayer->id);
                }
            }
        }

        return view('livewire.pages.more-page', [
            'socialLinks' => $socialLinks,
            'appVersion' => $appVersion,
            'user' => $currentUser,
            'winRate' => $winRate,
            'inviteUrl' => $inviteUrl,
            'qrSvg' => $qrSvg,
            'transactions' => $transactions,
            'incomingRequests' => $incomingRequests,
            'friendsList' => $friendsList,
            'searchResults' => $searchResults,
            'promptedPlayer' => $promptedPlayer,
            'promptedRelation' => $promptedRelation,
        ])->layout('components.layouts.app');
    }

    /**
     * Resolve relationship state between current user and target user.
     *
     * @return array{status: string, friendship_id: int|null}
     */
    protected function resolveRelationState(int $currentUserId, int $targetUserId): array
    {
        $f = Friendship::betweenUsers($currentUserId, $targetUserId)->first();
        if (!$f) {
            return ['status' => 'none', 'friendship_id' => null];
        }

        if ($f->status === Friendship::STATUS_ACCEPTED) {
            return ['status' => 'accepted', 'friendship_id' => $f->id];
        }

        if ($f->status === Friendship::STATUS_PENDING) {
            $status = ((int) $f->sender_id === $currentUserId) ? 'outgoing_pending' : 'incoming_pending';
            return ['status' => $status, 'friendship_id' => $f->id];
        }

        return ['status' => 'none', 'friendship_id' => $f->id];
    }

    /**
     * Compute settled Head-to-Head battle scores between current user and each friend.
     *
     * @param  array<int, int>  $friendIds
     * @return array<int, array{me: int, them: int}>
     */
    protected function computeHeadToHeadStats(int $currentUserId, array $friendIds): array
    {
        $stats = [];
        if (empty($friendIds)) {
            return $stats;
        }

        $settledBattles = Battle::where('status', Battle::STATUS_SETTLED)
            ->where(function ($q) use ($currentUserId, $friendIds) {
                $q->where(function ($pair) use ($currentUserId, $friendIds) {
                    $pair->where('creator_id', $currentUserId)->whereIn('opponent_id', $friendIds);
                })->orWhere(function ($pair) use ($currentUserId, $friendIds) {
                    $pair->where('opponent_id', $currentUserId)->whereIn('creator_id', $friendIds);
                });
            })
            ->select(['creator_id', 'opponent_id', 'winner_id'])
            ->get();

        foreach ($settledBattles as $battle) {
            $friendId = ((int) $battle->creator_id === $currentUserId)
                ? (int) $battle->opponent_id
                : (int) $battle->creator_id;

            if (!isset($stats[$friendId])) {
                $stats[$friendId] = ['me' => 0, 'them' => 0];
            }

            if ((int) $battle->winner_id === $currentUserId) {
                $stats[$friendId]['me']++;
            } elseif ((int) $battle->winner_id === $friendId) {
                $stats[$friendId]['them']++;
            }
        }

        return $stats;
    }
}
