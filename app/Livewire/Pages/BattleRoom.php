<?php

namespace App\Livewire\Pages;

use App\Models\SquadBattle;
use App\Models\SquadBattlePick;
use App\Services\CricGemsService;
use App\Services\SquadBattleService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Livewire\Component;

class BattleRoom extends Component
{
    public string $slug;
    public ?int $swappingPickId = null;
    public ?string $actionError = null;
    public ?string $actionSuccess = null;

    public function mount(string $slug, SquadBattleService $battleService, CricGemsService $cricGemsService): void
    {
        $this->slug = $slug;

        if (Auth::check()) {
            $cricGemsService->syncOnAuthenticatedVisit(Auth::user());
        }

        $battle = SquadBattle::where('slug', $slug)->firstOrFail();

        if (Auth::check() && $battle->isParticipant(Auth::id())) {
            $battle = $battleService->touchPresence($battle, Auth::user());
        } elseif (in_array($battle->status, [SquadBattle::STATUS_PENDING_ACCEPT, SquadBattle::STATUS_DRAFTING], true) && !$battle->isBeforeLock()) {
            $battle = $battleService->expireIfIncomplete($battle);
        }

        if ($battle->status === SquadBattle::STATUS_LOCKED_LIVE) {
            $battleService->syncLiveAndFinalScores($battle);
        }
    }

    public function pollRoom(SquadBattleService $battleService): void
    {
        $battle = SquadBattle::where('slug', $this->slug)->first();
        if (!$battle) {
            return;
        }

        if (Auth::check() && $battle->isParticipant(Auth::id())) {
            $battle = $battleService->touchPresence($battle, Auth::user());
        } elseif (in_array($battle->status, [SquadBattle::STATUS_PENDING_ACCEPT, SquadBattle::STATUS_DRAFTING], true) && !$battle->isBeforeLock()) {
            $battle = $battleService->expireIfIncomplete($battle);
        }

        if ($battle->status === SquadBattle::STATUS_LOCKED_LIVE) {
            $battleService->syncLiveAndFinalScores($battle);
        }

        if ($battle->isBeforeLock() && in_array($battle->status, [SquadBattle::STATUS_PENDING_ACCEPT, SquadBattle::STATUS_DRAFTING, SquadBattle::STATUS_LOCKED_LIVE], true)) {
            $battleService->syncPlayingXiAndNotify($battle);
        }
    }

    public function pollLiveScores(SquadBattleService $battleService): void
    {
        $battle = SquadBattle::where('slug', $this->slug)->first();
        if (!$battle) {
            return;
        }

        if ($battle->status === SquadBattle::STATUS_LOCKED_LIVE) {
            $battleService->syncLiveAndFinalScores($battle);
            if ($battle->isBeforeLock()) {
                $battleService->syncPlayingXiAndNotify($battle);
            }
        }
    }

    public function confirmFinalLineup(): void
    {
        $user = Auth::user();
        if (!$user) {
            return;
        }

        $battle = SquadBattle::where('slug', $this->slug)->firstOrFail();
        if ($battle->isParticipant($user->id)) {
            Cache::put("battle:{$battle->id}:confirmed:{$user->id}", true, 86400);
            $this->actionSuccess = '🔒 Your Final Playing XI lineup has been confirmed!';
        }
    }

    public function acceptChallenge(SquadBattleService $battleService): void
    {
        $this->actionError = null;
        $this->actionSuccess = null;

        $user = Auth::user();
        if (!$user) {
            $this->actionError = 'Please sign in with Google to accept this battle.';
            return;
        }

        $battle = SquadBattle::where('slug', $this->slug)->firstOrFail();

        try {
            $battle = $battleService->acceptChallenge($battle, $user);
            $tossName = $battle->tossWinner?->captainTitle() ?? 'Captain';
            $this->actionSuccess = "🪙 Coin Toss Complete! {$tossName} won the toss and gets the 1st pick!";
        } catch (\Throwable $e) {
            $this->actionError = $e->getMessage();
        }
    }

    public function declineChallenge(SquadBattleService $battleService): void
    {
        $this->actionError = null;
        $user = Auth::user();
        if (!$user) {
            return;
        }

        $battle = SquadBattle::where('slug', $this->slug)->firstOrFail();

        try {
            $battleService->declineChallenge($battle, $user);
            $this->actionSuccess = 'You declined this battle challenge.';
        } catch (\Throwable $e) {
            $this->actionError = $e->getMessage();
        }
    }

    public function pickSquadPlayer(string $teamKey, int $index, SquadBattleService $battleService): void
    {
        $this->actionError = null;
        $this->actionSuccess = null;

        $user = Auth::user();
        if (!$user) {
            $this->actionError = 'Please sign in to participate in this draft.';
            return;
        }

        $battle = SquadBattle::with('picks')->where('slug', $this->slug)->firstOrFail();
        $squadsData = $battleService->getMatchSquadsData($battle);

        $player = $squadsData[$teamKey]['players'][$index] ?? null;
        if (!$player) {
            $this->actionError = 'Selected player could not be found in squad list.';
            return;
        }

        try {
            $battle = $battleService->pickPlayer($battle, $user, $player);
            if ($battle->status === SquadBattle::STATUS_LOCKED_LIVE) {
                $this->actionSuccess = '✅ Both squads are complete! Battle is now locked and live!';
            } else {
                $this->actionSuccess = "Picked {$player['name']}!";
            }
        } catch (\Throwable $e) {
            $this->actionError = $e->getMessage();
        }
    }

    public function startSwap(int $pickId): void
    {
        $this->actionError = null;
        $this->actionSuccess = null;
        $this->swappingPickId = $pickId;
    }

    public function cancelSwap(): void
    {
        $this->swappingPickId = null;
    }

    public function confirmSwapPlayer(string $teamKey, int $index, SquadBattleService $battleService): void
    {
        $this->actionError = null;
        $this->actionSuccess = null;

        $user = Auth::user();
        if (!$user || !$this->swappingPickId) {
            return;
        }

        $battle = SquadBattle::with('picks')->where('slug', $this->slug)->firstOrFail();
        $squadsData = $battleService->getMatchSquadsData($battle);
        $replacement = $squadsData[$teamKey]['players'][$index] ?? null;

        if (!$replacement) {
            $this->actionError = 'Replacement player not found.';
            return;
        }

        try {
            $battleService->swapBenchedPlayer($battle, $user, $this->swappingPickId, $replacement);
            $this->swappingPickId = null;
            $this->actionSuccess = "🔄 Swapped in {$replacement['name']} (● Playing)!";
        } catch (\Throwable $e) {
            $this->actionError = $e->getMessage();
        }
    }

    public function render(SquadBattleService $battleService)
    {
        $battle = SquadBattle::with([
            'challenger',
            'opponent',
            'tossWinner',
            'currentTurnUser',
            'winner',
            'picks',
        ])->where('slug', $this->slug)->firstOrFail();

        $currentUser = Auth::user();
        $isParticipant = $currentUser ? $battle->isParticipant($currentUser->id) : false;

        $squadsData = $battle->isBeforeLock()
            ? $battleService->syncPlayingXiAndNotify($battle)
            : $battleService->getMatchSquadsData($battle);
        $battle->load('picks');

        $finalLineupConfirmed = ($currentUser && $isParticipant)
            ? (bool) Cache::get("battle:{$battle->id}:confirmed:{$currentUser->id}", false)
            : false;

        $challengerPicks = $battle->picks->where('user_id', $battle->challenger_id)->values();
        $opponentPicks = $battle->picks->where('user_id', $battle->opponent_id)->values();

        // Map picked players by normalized name + team for fast O(1) UI lock badges
        $pickedMap = [];
        foreach ($battle->picks as $pick) {
            $key = $battleService->normalizePlayerName($pick->player_name) . '|' . mb_strtolower(trim($pick->player_team));
            $pickedByCaptain = (int) $pick->user_id === (int) $battle->challenger_id
                ? ($battle->challenger?->captainTitle() ?? 'Captain 1')
                : ($battle->opponent?->captainTitle() ?? 'Captain 2');
            $pickedMap[$key] = [
                'pick_id' => $pick->id,
                'user_id' => (int) $pick->user_id,
                'captain_title' => $pickedByCaptain,
            ];
        }

        $otherCaptainOnline = $isParticipant && $currentUser
            ? $battleService->isOtherCaptainOnline($battle, $currentUser->id)
            : true;

        $isDraftPaused = $battle->status === SquadBattle::STATUS_DRAFTING && $isParticipant && !$otherCaptainOnline;
        $isMyTurn = $battle->status === SquadBattle::STATUS_DRAFTING
            && $currentUser
            && (int) $battle->current_turn_user_id === (int) $currentUser->id
            && !$isDraftPaused
            && $battle->isBeforeLock();

        $otherCaptainName = '';
        if ($currentUser && $isParticipant) {
            $otherCaptainName = (int) $currentUser->id === (int) $battle->challenger_id
                ? ($battle->opponent?->captainTitle() ?? 'Opponent')
                : ($battle->challenger?->captainTitle() ?? 'Challenger');
        }

        $challengerDisplay = $battle->challenger?->captainDisplayName() ?? 'Captain 1';
        $opponentDisplay = $battle->opponent?->captainDisplayName() ?? 'Captain 2';
        $challengerTitle = $battle->challenger?->captainTitle() ?? 'Captain 1';
        $opponentTitle = $battle->opponent?->captainTitle() ?? 'Captain 2';

        $winnerBannerText = null;
        $winnerTeamLabel = null;
        $winnerCaptainName = null;
        $loserCaptainName = null;
        $winnerRuns = 0;
        $loserRuns = 0;

        if ($battle->status === SquadBattle::STATUS_SETTLED) {
            if ($battle->winner_id !== null && (int) $battle->run_margin > 0) {
                $isChallengerWinner = (int) $battle->winner_id === (int) $battle->challenger_id;
                $winnerTeamLabel = $isChallengerWinner ? 'Team 1' : 'Team 2';
                $winnerCaptainName = $isChallengerWinner ? $challengerTitle : $opponentTitle;
                $loserCaptainName = $isChallengerWinner ? $opponentTitle : $challengerTitle;
                $winnerRuns = $isChallengerWinner ? (int) $battle->challenger_runs : (int) $battle->opponent_runs;
                $loserRuns = $isChallengerWinner ? (int) $battle->opponent_runs : (int) $battle->challenger_runs;
                $winnerBannerText = "{$winnerTeamLabel} ({$winnerCaptainName}) won the match by {$battle->run_margin} runs";
            } else {
                $winnerBannerText = "Match Tied! Both Captains scored {$battle->challenger_runs} runs";
            }
        }

        // SEO Metadata + SportsEvent JSON-LD
        $pageTitle = "{$challengerDisplay} vs {$opponentDisplay} Match Battle - {$battle->match_title} ({$battle->modeLabel()}) | Cricwire";
        $pageDesc = $battle->status === SquadBattle::STATUS_SETTLED && $winnerBannerText
            ? "{$winnerBannerText} in {$battle->match_title} ({$battle->modeLabel()} {$battle->match_format}). View full player runs scorecard on Cricwire!"
            : "Live {$battle->modeLabel()} Captain vs Captain Squad Runs Battle between {$challengerTitle} and {$opponentTitle} for {$battle->match_title} ({$battle->match_format}) on Cricwire.";

        $sportsEventSchema = [
            '@context' => 'https://schema.org',
            '@type' => 'SportsEvent',
            'name' => "{$challengerTitle} vs {$opponentTitle} — {$battle->match_title} ({$battle->modeLabel()})",
            'description' => $pageDesc,
            'sport' => 'Cricket',
            'url' => route('games.battle.show', $battle->slug),
            'startDate' => $battle->match_starts_at?->toIso8601String(),
            'competitor' => [
                ['@type' => 'Person', 'name' => $challengerTitle],
                ['@type' => 'Person', 'name' => $opponentTitle],
            ],
        ];

        $seoData = [
            'title' => $pageTitle,
            'description' => $pageDesc,
            'canonical' => route('games.battle.show', $battle->slug),
            'robots' => in_array($battle->status, [SquadBattle::STATUS_LOCKED_LIVE, SquadBattle::STATUS_SETTLED], true)
                ? 'index, follow'
                : 'noindex, follow',
            'schemas' => [$sportsEventSchema],
        ];

        return view('livewire.pages.battle-room', [
            'battle' => $battle,
            'currentUser' => $currentUser,
            'isParticipant' => $isParticipant,
            'squadsData' => $squadsData,
            'challengerPicks' => $challengerPicks,
            'opponentPicks' => $opponentPicks,
            'pickedMap' => $pickedMap,
            'otherCaptainOnline' => $otherCaptainOnline,
            'isDraftPaused' => $isDraftPaused,
            'isMyTurn' => $isMyTurn,
            'otherCaptainName' => $otherCaptainName,
            'challengerTitle' => $challengerTitle,
            'opponentTitle' => $opponentTitle,
            'winnerBannerText' => $winnerBannerText,
            'winnerTeamLabel' => $winnerTeamLabel,
            'winnerCaptainName' => $winnerCaptainName,
            'loserCaptainName' => $loserCaptainName,
            'winnerRuns' => $winnerRuns,
            'loserRuns' => $loserRuns,
            'isBeforeLock' => $battle->isBeforeLock(),
            'playingXiAnnounced' => (bool) ($squadsData['playing_xi_announced'] ?? false),
            'swappingPick' => $this->swappingPickId ? $battle->picks->firstWhere('id', $this->swappingPickId) : null,
            'finalLineupConfirmed' => $finalLineupConfirmed,
        ])->layout('layouts.app', [
            'seoData' => $seoData,
            'title' => $seoData['title'],
        ]);
    }
}
