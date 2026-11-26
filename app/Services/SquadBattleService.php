<?php

namespace App\Services;

use App\Helpers\CricketMatchFormatter;
use App\Helpers\CricketSlugHelper;
use App\Mail\BattleChallengeMail;
use App\Models\CompletedMatchScorecard;
use App\Models\FinishedMatch;
use App\Models\Friendship;
use App\Models\ManagedMatch;
use App\Models\SquadBattle;
use App\Models\SquadBattlePick;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\Cricket\CricketLiveApiService;
use App\Services\Cricket\MatchStateNormalizer;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class SquadBattleService
{
    public const MIN_ENTRY_GEMS = 100;
    public const PRESENCE_TIMEOUT_SECONDS = 10;

    public function __construct(
        protected CricketLiveApiService $apiService,
        protected CricGemsService $cricGemsService
    ) {}

    /**
     * Load upcoming T20 & ODI matches ONLY (strictly excludes Test / First-Class)
     * using existing DB & cached feeds (0 external API calls).
     */
    public function getEligibleUpcomingMatches(string $filter = 'today'): array
    {
        $nowIst = Carbon::now('Asia/Kolkata');
        $todayDate = $nowIst->toDateString();

        $mergedById = [];

        // 1. Load from Redis home upcoming + static JSON fallback
        $homeData = $this->apiService->getHomeFromRedis();
        $feedUpcoming = $homeData['upcoming'] ?? [];
        if (empty($feedUpcoming)) {
            $staticHomeFile = public_path('live-data/home.json');
            if (file_exists($staticHomeFile)) {
                $decoded = json_decode(@file_get_contents($staticHomeFile), true);
                if (is_array($decoded) && !empty($decoded['upcoming'])) {
                    $feedUpcoming = $decoded['upcoming'];
                }
            }
        }

        foreach ($this->apiService->filterAllowedMatches($feedUpcoming) as $rawMatch) {
            if (!is_array($rawMatch)) {
                continue;
            }
            $formatted = CricketMatchFormatter::formatMatchForCard($rawMatch);
            $mId = (string) ($formatted['match_id'] ?? $formatted['id'] ?? '');
            if ($mId !== '') {
                $mergedById[$mId] = $formatted;
            }
        }

        // 2. Load/merge from managed_matches table (covered & not skipped)
        $managedRows = ManagedMatch::query()
            ->where('is_covered', true)
            ->where('series_skipped', false)
            ->whereNotNull('start_time_ist')
            ->where('start_time_ist', '>', $nowIst->copy()->addMinutes(5))
            ->orderBy('start_time_ist')
            ->get();

        foreach ($managedRows as $mm) {
            $mId = (string) $mm->match_id;
            $base = $mergedById[$mId] ?? [];
            $teamA = $base['team_a'] ?? $mm->team_a ?: 'Team A';
            $teamB = $base['team_b'] ?? $mm->team_b ?: 'Team B';

            $mergedById[$mId] = array_merge($base, [
                'match_id' => $mId,
                'id' => $mId,
                'series_name' => $base['series_name'] ?? $mm->series_name,
                'series' => $base['series'] ?? $mm->series_name,
                'match_desc' => $base['match_desc'] ?? $mm->match_desc ?? $mm->match_number ?? 'Match',
                'format' => $mm->format ?: ($base['format'] ?? ''),
                'team_a' => $teamA,
                'team_b' => $teamB,
                'team_a_short' => $base['team_a_short'] ?? CricketLiveApiService::generateTeamShort($teamA),
                'team_b_short' => $base['team_b_short'] ?? CricketLiveApiService::generateTeamShort($teamB),
                'team_a_img' => $base['team_a_img'] ?? '',
                'team_b_img' => $base['team_b_img'] ?? '',
                'state' => $mm->state ?: ($base['state'] ?? 'Upcoming'),
                'start_time_ist' => $mm->start_time_ist->copy()->timezone('Asia/Kolkata')->format('Y-m-d H:i:s'),
            ]);
        }

        $eligible = [];

        foreach ($mergedById as $match) {
            if (MatchStateNormalizer::isFinished($match) || MatchStateNormalizer::isLive($match)) {
                continue;
            }

            $normalizedFormat = $this->normalizeAllowedFormat($match);
            if ($normalizedFormat === null) {
                continue;
            }

            $startIst = $this->resolveStartTimeIst($match);
            if ($startIst === null) {
                continue;
            }

            $locksAtIst = $startIst->copy()->subMinutes(5);
            if ($locksAtIst->lte($nowIst)) {
                continue;
            }

            if ($filter === 'today' && $startIst->toDateString() !== $todayDate) {
                continue;
            }

            $teamA = trim((string) ($match['team_a'] ?? 'Team A'));
            $teamB = trim((string) ($match['team_b'] ?? 'Team B'));
            $teamAShort = trim((string) ($match['team_a_short'] ?? CricketLiveApiService::generateTeamShort($teamA)));
            $teamBShort = trim((string) ($match['team_b_short'] ?? CricketLiveApiService::generateTeamShort($teamB)));

            $eligible[] = [
                'match_id' => (string) $match['match_id'],
                'match_title' => "{$teamA} vs {$teamB}",
                'short_title' => "{$teamAShort} vs {$teamBShort}",
                'format' => $normalizedFormat,
                'series_name' => (string) ($match['series_name'] ?? $match['series'] ?? 'Cricket Series'),
                'series_slug' => CricketSlugHelper::makeSeriesSlug($match),
                'match_slug' => CricketSlugHelper::makeMatchSlug($match, $match['match_id']),
                'match_desc' => (string) ($match['match_desc'] ?? 'Match'),
                'team_a' => $teamA,
                'team_b' => $teamB,
                'team_a_short' => $teamAShort,
                'team_b_short' => $teamBShort,
                'team_a_img' => (string) ($match['team_a_img'] ?? ''),
                'team_b_img' => (string) ($match['team_b_img'] ?? ''),
                'venue' => (string) ($match['venue'] ?? ''),
                'start_time_ist' => $startIst->format('Y-m-d H:i:s'),
                'start_timestamp' => $startIst->timestamp,
                'ist_time_label' => $startIst->format('d M, h:i A') . ' IST',
                'locks_at_ist' => $locksAtIst->format('Y-m-d H:i:s'),
                'locks_at_label' => $locksAtIst->format('h:i A') . ' IST',
            ];
        }

        usort($eligible, fn (array $a, array $b) => $a['start_timestamp'] <=> $b['start_timestamp']);

        if ($filter === 'top10') {
            return array_slice($eligible, 0, 10);
        }

        return array_values($eligible);
    }

    /**
     * Validate and create a Captain vs Captain Squad Battle challenge.
     */
    public function createChallenge(User $challenger, User $opponent, array $matchData, int $teamSize): SquadBattle
    {
        if ((int) $challenger->id === (int) $opponent->id) {
            throw new RuntimeException('You cannot challenge yourself.');
        }

        if (!in_array($teamSize, [3, 5], true)) {
            throw new RuntimeException('Team size must be 5v5 or 3v3.');
        }

        $format = $this->normalizeAllowedFormat($matchData);
        if ($format === null) {
            throw new RuntimeException('Only T20 and ODI upcoming matches are eligible for Captain Battles. Test matches are excluded.');
        }

        $isFriend = Friendship::betweenUsers($challenger->id, $opponent->id)
            ->where('status', Friendship::STATUS_ACCEPTED)
            ->exists();
        if (!$isFriend) {
            throw new RuntimeException('You can only challenge accepted friends.');
        }

        $freshChallenger = User::findOrFail($challenger->id);
        $freshOpponent = User::findOrFail($opponent->id);

        if ((int) $freshChallenger->cric_gems < self::MIN_ENTRY_GEMS) {
            throw new RuntimeException('You need at least 100 CricGems to start a Squad Battle.');
        }
        if ((int) $freshOpponent->cric_gems < self::MIN_ENTRY_GEMS) {
            throw new RuntimeException("{$freshOpponent->captainTitle()} needs at least 100 CricGems to accept a battle.");
        }

        $startTimeIst = Carbon::parse($matchData['start_time_ist'], 'Asia/Kolkata');
        $locksAt = $startTimeIst->copy()->subMinutes(5);
        if (Carbon::now('Asia/Kolkata')->gte($locksAt)) {
            throw new RuntimeException('Draft lock time (5 minutes before match start) has already passed for this match.');
        }

        $teamA = trim((string) ($matchData['team_a'] ?? 'Team A'));
        $teamB = trim((string) ($matchData['team_b'] ?? 'Team B'));
        $teamAShort = trim((string) ($matchData['team_a_short'] ?? CricketLiveApiService::generateTeamShort($teamA)));
        $teamBShort = trim((string) ($matchData['team_b_short'] ?? CricketLiveApiService::generateTeamShort($teamB)));

        $slug = $this->generateUniqueSlug(
            $freshChallenger->captainDisplayName(),
            $freshOpponent->captainDisplayName(),
            $teamAShort,
            $teamBShort,
            $format
        );

        $battle = DB::transaction(function () use ($freshChallenger, $freshOpponent, $matchData, $teamSize, $format, $teamA, $teamB, $startTimeIst, $locksAt, $slug) {
            $created = SquadBattle::create([
                'slug' => $slug,
                'match_id' => (string) $matchData['match_id'],
                'match_title' => (string) ($matchData['match_title'] ?? "{$teamA} vs {$teamB}"),
                'match_format' => $format,
                'series_slug' => $matchData['series_slug'] ?? CricketSlugHelper::makeSeriesSlug($matchData),
                'match_slug' => $matchData['match_slug'] ?? CricketSlugHelper::makeMatchSlug($matchData, $matchData['match_id']),
                'team_a' => $teamA,
                'team_b' => $teamB,
                'team_size' => $teamSize,
                'challenger_id' => $freshChallenger->id,
                'opponent_id' => $freshOpponent->id,
                'challenger_last_seen_at' => now(),
                'status' => SquadBattle::STATUS_PENDING_ACCEPT,
                'match_starts_at' => $startTimeIst->copy()->utc(),
                'locks_at' => $locksAt->copy()->utc(),
            ]);

            UserNotification::create([
                'user_id' => $freshOpponent->id,
                'type' => UserNotification::TYPE_BATTLE_CHALLENGE,
                'title' => "⚔️ {$freshChallenger->captainTitle()} challenged you!",
                'body' => "{$created->match_title} ({$created->modeLabel()} • {$created->match_format}). Accept & join the live draft room before " . $locksAt->format('h:i A') . ' IST.',
                'action_url' => route('games.battle.show', $created->slug),
            ]);

            return $created;
        });

        $battle->load(['challenger', 'opponent']);

        if (!empty($freshOpponent->email)) {
            try {
                Mail::to($freshOpponent->email)->send(new BattleChallengeMail($battle));
            } catch (\Throwable $e) {
                Log::warning("SquadBattleService: Failed sending BattleChallengeMail for battle #{$battle->id}: " . $e->getMessage());
            }
        }

        return $battle;
    }

    /**
     * Opponent accepts challenge, triggering System Coin Toss and starting the live draft.
     */
    public function acceptChallenge(SquadBattle $battle, User $opponent): SquadBattle
    {
        return DB::transaction(function () use ($battle, $opponent) {
            $locked = SquadBattle::where('id', $battle->id)->lockForUpdate()->firstOrFail();

            if ((int) $locked->opponent_id !== (int) $opponent->id) {
                throw new RuntimeException('Only the challenged Captain can accept this battle.');
            }

            if ($locked->status !== SquadBattle::STATUS_PENDING_ACCEPT) {
                throw new RuntimeException('This challenge is no longer pending acceptance.');
            }

            if (!$locked->isBeforeLock()) {
                $locked->update(['status' => SquadBattle::STATUS_EXPIRED_INCOMPLETE]);
                throw new RuntimeException('Match draft lock deadline has passed. Challenge expired.');
            }

            $challengerUser = User::where('id', $locked->challenger_id)->lockForUpdate()->firstOrFail();
            $opponentUser = User::where('id', $locked->opponent_id)->lockForUpdate()->firstOrFail();

            if ((int) $challengerUser->cric_gems < self::MIN_ENTRY_GEMS) {
                throw new RuntimeException("{$challengerUser->captainTitle()} no longer meets the 100 CricGems minimum.");
            }
            if ((int) $opponentUser->cric_gems < self::MIN_ENTRY_GEMS) {
                throw new RuntimeException('You need at least 100 CricGems to accept this challenge.');
            }

            $tossWinnerId = random_int(0, 1) === 0
                ? (int) $locked->challenger_id
                : (int) $locked->opponent_id;

            $locked->update([
                'status' => SquadBattle::STATUS_DRAFTING,
                'toss_winner_id' => $tossWinnerId,
                'current_turn_user_id' => $tossWinnerId,
                'opponent_last_seen_at' => now(),
            ]);

            UserNotification::create([
                'user_id' => $challengerUser->id,
                'type' => UserNotification::TYPE_BATTLE_ACCEPTED,
                'title' => "🪙 Challenge Accepted by {$opponentUser->captainTitle()}!",
                'body' => "System toss done for {$locked->match_title} ({$locked->modeLabel()}). Enter the live draft room now!",
                'action_url' => route('games.battle.show', $locked->slug),
            ]);

            return $locked->fresh(['challenger', 'opponent', 'tossWinner', 'currentTurnUser', 'picks']);
        });
    }

    /**
     * Opponent declines a pending challenge.
     */
    public function declineChallenge(SquadBattle $battle, User $opponent): SquadBattle
    {
        return DB::transaction(function () use ($battle, $opponent) {
            $locked = SquadBattle::where('id', $battle->id)->lockForUpdate()->firstOrFail();

            if ((int) $locked->opponent_id !== (int) $opponent->id) {
                throw new RuntimeException('Only the challenged Captain can decline this battle.');
            }

            if ($locked->status === SquadBattle::STATUS_PENDING_ACCEPT) {
                $locked->update(['status' => SquadBattle::STATUS_DECLINED]);
            }

            return $locked;
        });
    }

    /**
     * Update the viewer Captain's live presence heartbeat and enforce draft lock expiry.
     */
    public function touchPresence(SquadBattle $battle, ?User $user): SquadBattle
    {
        $now = now();

        if ($user && $battle->isParticipant($user->id)) {
            if ((int) $battle->challenger_id === (int) $user->id) {
                SquadBattle::where('id', $battle->id)->update(['challenger_last_seen_at' => $now]);
                $battle->challenger_last_seen_at = $now;
            } elseif ((int) $battle->opponent_id === (int) $user->id) {
                SquadBattle::where('id', $battle->id)->update(['opponent_last_seen_at' => $now]);
                $battle->opponent_last_seen_at = $now;
            }
        }

        if (
            in_array($battle->status, [SquadBattle::STATUS_PENDING_ACCEPT, SquadBattle::STATUS_DRAFTING], true)
            && !$battle->isBeforeLock()
        ) {
            $battle = $this->expireIfIncomplete($battle);
        }

        return $battle;
    }

    /**
     * Check whether the other Captain is actively present on the Draft Room screen.
     */
    public function isOtherCaptainOnline(SquadBattle $battle, int $viewerUserId): bool
    {
        $threshold = now()->subSeconds(self::PRESENCE_TIMEOUT_SECONDS);

        if ((int) $battle->challenger_id === $viewerUserId) {
            return $battle->opponent_last_seen_at !== null && $battle->opponent_last_seen_at->gte($threshold);
        }

        if ((int) $battle->opponent_id === $viewerUserId) {
            return $battle->challenger_last_seen_at !== null && $battle->challenger_last_seen_at->gte($threshold);
        }

        return false;
    }

    /**
     * Pick a player during the live turn-by-turn draft.
     */
    public function pickPlayer(SquadBattle $battle, User $captain, array $player): SquadBattle
    {
        return DB::transaction(function () use ($battle, $captain, $player) {
            $locked = SquadBattle::where('id', $battle->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== SquadBattle::STATUS_DRAFTING) {
                throw new RuntimeException('Drafting is not currently active for this battle.');
            }

            if (!$locked->isBeforeLock()) {
                $locked->update([
                    'status' => SquadBattle::STATUS_EXPIRED_INCOMPLETE,
                    'current_turn_user_id' => null,
                ]);
                throw new RuntimeException('Draft lock time reached. Incomplete draft expired with 0 CricGems deducted.');
            }

            if ((int) $locked->current_turn_user_id !== (int) $captain->id) {
                throw new RuntimeException('It is not your turn to pick yet.');
            }

            // Touch picker presence and ensure opponent is also present (NO auto-pick / pause if away)
            $now = now();
            if ((int) $locked->challenger_id === (int) $captain->id) {
                $locked->challenger_last_seen_at = $now;
            } else {
                $locked->opponent_last_seen_at = $now;
            }

            if (!$this->isOtherCaptainOnline($locked, (int) $captain->id)) {
                $locked->save();
                throw new RuntimeException('Draft is paused because the other Captain left the screen.');
            }

            $existingPicks = SquadBattlePick::where('squad_battle_id', $locked->id)->get();
            $captainPicksCount = $existingPicks->where('user_id', $captain->id)->count();
            if ($captainPicksCount >= (int) $locked->team_size) {
                throw new RuntimeException('Your squad is already full.');
            }

            $playerName = trim((string) ($player['name'] ?? ''));
            $playerTeam = trim((string) ($player['team'] ?? ''));
            if ($playerName === '' || $playerTeam === '') {
                throw new RuntimeException('Invalid player selected.');
            }

            $alreadyPicked = $existingPicks->contains(function (SquadBattlePick $pick) use ($playerName, $playerTeam) {
                return $this->normalizePlayerName($pick->player_name) === $this->normalizePlayerName($playerName)
                    && mb_strtolower(trim($pick->player_team)) === mb_strtolower($playerTeam);
            });

            if ($alreadyPicked) {
                throw new RuntimeException("{$playerName} has already been picked and is locked.");
            }

            SquadBattlePick::create([
                'squad_battle_id' => $locked->id,
                'user_id' => $captain->id,
                'player_id' => isset($player['player_id']) && $player['player_id'] !== '' ? (string) $player['player_id'] : null,
                'player_name' => $playerName,
                'player_team' => $playerTeam,
                'player_role' => isset($player['role']) && $player['role'] !== '' ? mb_substr((string) $player['role'], 0, 50) : null,
                'pick_order' => $existingPicks->count() + 1,
                'is_playing_xi' => (bool) ($player['is_playing_xi'] ?? false),
                'runs_scored' => 0,
                'did_not_bat' => true,
            ]);

            $totalPicksAfter = $existingPicks->count() + 1;
            $requiredTotal = (int) $locked->team_size * 2;

            if ($totalPicksAfter >= $requiredTotal) {
                $locked->status = SquadBattle::STATUS_LOCKED_LIVE;
                $locked->current_turn_user_id = null;
            } else {
                $otherId = $locked->otherCaptainId((int) $captain->id);
                $otherCount = $existingPicks->where('user_id', $otherId)->count();
                if ($otherCount < (int) $locked->team_size) {
                    $locked->current_turn_user_id = $otherId;
                }
            }

            $locked->save();

            return $locked->fresh(['challenger', 'opponent', 'tossWinner', 'currentTurnUser', 'picks']);
        });
    }

    /**
     * Replace a picked player who turned out to be Benched (not in Playing XI)
     * with an unpicked Playing XI player before locks_at (Match Start - 5 mins).
     */
    public function swapBenchedPlayer(SquadBattle $battle, User $captain, int $pickId, array $replacementPlayer): SquadBattle
    {
        $squadsData = $this->getMatchSquadsData($battle);
        if (empty($squadsData['playing_xi_announced'])) {
            throw new RuntimeException('Playing XI has not been announced yet.');
        }

        return DB::transaction(function () use ($battle, $captain, $pickId, $replacementPlayer) {
            $locked = SquadBattle::where('id', $battle->id)->lockForUpdate()->firstOrFail();

            if (!in_array($locked->status, [SquadBattle::STATUS_DRAFTING, SquadBattle::STATUS_LOCKED_LIVE], true)) {
                throw new RuntimeException('Bench swaps are not available in this battle state.');
            }

            if (!$locked->isBeforeLock()) {
                throw new RuntimeException('Match lock deadline has passed. Squads are now permanently locked.');
            }

            $pick = SquadBattlePick::where('id', $pickId)
                ->where('squad_battle_id', $locked->id)
                ->where('user_id', $captain->id)
                ->firstOrFail();

            if ($pick->is_playing_xi) {
                throw new RuntimeException('Only benched players (not in Playing XI) can be replaced.');
            }

            if (empty($replacementPlayer['is_playing_xi'])) {
                throw new RuntimeException('Replacement player must be in the announced Playing XI (● Playing).');
            }

            $repName = trim((string) ($replacementPlayer['name'] ?? ''));
            $repTeam = trim((string) ($replacementPlayer['team'] ?? ''));
            if ($repName === '' || $repTeam === '') {
                throw new RuntimeException('Invalid replacement player selected.');
            }

            $alreadyPicked = SquadBattlePick::where('squad_battle_id', $locked->id)
                ->where('id', '!=', $pick->id)
                ->get()
                ->contains(function (SquadBattlePick $other) use ($repName, $repTeam) {
                    return $this->normalizePlayerName($other->player_name) === $this->normalizePlayerName($repName)
                        && mb_strtolower(trim($other->player_team)) === mb_strtolower($repTeam);
                });

            if ($alreadyPicked) {
                throw new RuntimeException("{$repName} is already locked in this battle.");
            }

            $pick->update([
                'player_id' => isset($replacementPlayer['player_id']) && $replacementPlayer['player_id'] !== '' ? (string) $replacementPlayer['player_id'] : null,
                'player_name' => $repName,
                'player_team' => $repTeam,
                'player_role' => isset($replacementPlayer['role']) && $replacementPlayer['role'] !== '' ? mb_substr((string) $replacementPlayer['role'], 0, 50) : null,
                'is_playing_xi' => true,
            ]);

            return $locked->fresh(['challenger', 'opponent', 'tossWinner', 'currentTurnUser', 'picks']);
        });
    }

    /**
     * Load complete squads and Playing XI flags from existing DB/Redis/JSON caches ONLY (0 external API calls).
     *
     * @return array{playing_xi_announced: bool, team_a: array{name: string, players: array}, team_b: array{name: string, players: array}}
     */
    public function getMatchSquadsData(SquadBattle $battle): array
    {
        $matchId = (string) $battle->match_id;
        $archivedXI = $this->apiService->getArchivedPlayingXI($matchId);
        $preMatch = $this->apiService->getPreMatchArchivedData($matchId);

        $livePayload = Cache::get("cricket:live_match_payload:{$matchId}")
            ?? Cache::get("cricket:redis:match:{$matchId}");

        if (!$livePayload) {
            $staticMatchPath = public_path("live-data/match-{$matchId}.json");
            if (file_exists($staticMatchPath)) {
                $decoded = json_decode(@file_get_contents($staticMatchPath), true);
                if (is_array($decoded)) {
                    $livePayload = $decoded;
                }
            }
        }

        $xiTeamA = $archivedXI['team_a']['playing_xi'] ?? [];
        $xiTeamB = $archivedXI['team_b']['playing_xi'] ?? [];

        if (empty($xiTeamA) && !empty($livePayload['team_a_playing_xi']) && is_array($livePayload['team_a_playing_xi'])) {
            $xiTeamA = $livePayload['team_a_playing_xi'];
        }
        if (empty($xiTeamB) && !empty($livePayload['team_b_playing_xi']) && is_array($livePayload['team_b_playing_xi'])) {
            $xiTeamB = $livePayload['team_b_playing_xi'];
        }

        $preTeamA = $preMatch['squads']['team_a'] ?? [];
        $preTeamB = $preMatch['squads']['team_b'] ?? [];

        $preHasExplicitBench = !empty($preTeamA['bench']) || !empty($preTeamB['bench']);
        if ($preHasExplicitBench) {
            if (empty($xiTeamA) && !empty($preTeamA['playing_xi'])) {
                $xiTeamA = $preTeamA['playing_xi'];
            }
            if (empty($xiTeamB) && !empty($preTeamB['playing_xi'])) {
                $xiTeamB = $preTeamB['playing_xi'];
            }
        }

        $managed = ManagedMatch::where('match_id', (int) $matchId)->first();
        $playingXiAnnounced = (!empty($xiTeamA) && !empty($xiTeamB))
            || $preHasExplicitBench
            || ($managed && (bool) $managed->playing_xi_fetched);

        $xiNamesA = $this->extractPlayerNameMap($xiTeamA);
        $xiNamesB = $this->extractPlayerNameMap($xiTeamB);

        // Build full squad lists by merging Playing XI, pre-match squad/bench, live squad, and cached series squads
        $rawTeamAPlayers = array_merge(
            is_array($xiTeamA) ? $xiTeamA : [],
            is_array($preTeamA['playing_xi'] ?? null) ? $preTeamA['playing_xi'] : [],
            is_array($preTeamA['bench'] ?? null) ? $preTeamA['bench'] : [],
            is_array($preTeamA['squad'] ?? null) ? $preTeamA['squad'] : [],
            is_array($livePayload['team_a_squad'] ?? null) ? $livePayload['team_a_squad'] : [],
            is_array($livePayload['squads']['team_a']['playing_xi'] ?? null) ? $livePayload['squads']['team_a']['playing_xi'] : [],
            is_array($livePayload['squads']['team_a']['bench'] ?? null) ? $livePayload['squads']['team_a']['bench'] : []
        );

        $rawTeamBPlayers = array_merge(
            is_array($xiTeamB) ? $xiTeamB : [],
            is_array($preTeamB['playing_xi'] ?? null) ? $preTeamB['playing_xi'] : [],
            is_array($preTeamB['bench'] ?? null) ? $preTeamB['bench'] : [],
            is_array($preTeamB['squad'] ?? null) ? $preTeamB['squad'] : [],
            is_array($livePayload['team_b_squad'] ?? null) ? $livePayload['team_b_squad'] : [],
            is_array($livePayload['squads']['team_b']['playing_xi'] ?? null) ? $livePayload['squads']['team_b']['playing_xi'] : [],
            is_array($livePayload['squads']['team_b']['bench'] ?? null) ? $livePayload['squads']['team_b']['bench'] : []
        );

        // Fallback to cached series squads if either side is empty
        if ((empty($rawTeamAPlayers) || empty($rawTeamBPlayers)) && !empty($battle->series_slug)) {
            $seriesSquads = Cache::get("cricket:redis:series:{$battle->series_slug}:squads");
            if (is_array($seriesSquads)) {
                foreach ($seriesSquads as $teamBlock) {
                    if (!is_array($teamBlock) || empty($teamBlock['players'])) {
                        continue;
                    }
                    $tName = strtolower(trim((string) ($teamBlock['name'] ?? $teamBlock['team_name'] ?? '')));
                    if ($tName !== '' && (str_contains($tName, strtolower($battle->team_a)) || str_contains(strtolower($battle->team_a), $tName))) {
                        if (empty($rawTeamAPlayers)) {
                            $rawTeamAPlayers = $teamBlock['players'];
                        }
                    } elseif ($tName !== '' && (str_contains($tName, strtolower($battle->team_b)) || str_contains(strtolower($battle->team_b), $tName))) {
                        if (empty($rawTeamBPlayers)) {
                            $rawTeamBPlayers = $teamBlock['players'];
                        }
                    }
                }
            }
        }

        $teamAPlayers = $this->normalizeSquadList($rawTeamAPlayers, $battle->team_a, $xiNamesA, $playingXiAnnounced);
        $teamBPlayers = $this->normalizeSquadList($rawTeamBPlayers, $battle->team_b, $xiNamesB, $playingXiAnnounced);

        // Sync is_playing_xi onto existing battle picks whenever Playing XI is announced
        if ($playingXiAnnounced && $battle->relationLoaded('picks')) {
            $this->syncPicksPlayingXiStatus($battle, $teamAPlayers, $teamBPlayers);
        } elseif ($playingXiAnnounced) {
            $battle->load('picks');
            $this->syncPicksPlayingXiStatus($battle, $teamAPlayers, $teamBPlayers);
        }

        return [
            'playing_xi_announced' => $playingXiAnnounced,
            'team_a' => [
                'name' => $battle->team_a,
                'players' => $teamAPlayers,
            ],
            'team_b' => [
                'name' => $battle->team_b,
                'players' => $teamBPlayers,
            ],
        ];
    }

    /**
     * Sync live/final batting runs from local scorecards & auto-settle if match is completed (0 external API calls).
     */
    public function syncLiveAndFinalScores(SquadBattle $battle): SquadBattle
    {
        if (
            in_array($battle->status, [SquadBattle::STATUS_PENDING_ACCEPT, SquadBattle::STATUS_DRAFTING], true)
            && !$battle->isBeforeLock()
        ) {
            return $this->expireIfIncomplete($battle);
        }

        if (!in_array($battle->status, [SquadBattle::STATUS_LOCKED_LIVE, SquadBattle::STATUS_SETTLED], true)) {
            return $battle;
        }

        $matchId = (string) $battle->match_id;
        $scoreData = $this->loadLocalScorecardPayload($matchId);
        $battingRunsMap = $this->extractBattingRunsFromPayload($scoreData);

        $battle->load('picks');
        $challengerRuns = 0;
        $opponentRuns = 0;

        foreach ($battle->picks as $pick) {
            $matched = $this->findBatterRuns($pick->player_name, $battingRunsMap);
            if ($matched !== null) {
                $runs = $matched['runs'];
                $dnb = $matched['did_not_bat'];
            } else {
                $runs = 0;
                $dnb = true;
            }

            if ((int) $pick->runs_scored !== $runs || (bool) $pick->did_not_bat !== $dnb) {
                $pick->update([
                    'runs_scored' => $runs,
                    'did_not_bat' => $dnb,
                ]);
            }

            if ((int) $pick->user_id === (int) $battle->challenger_id) {
                $challengerRuns += $runs;
            } elseif ((int) $pick->user_id === (int) $battle->opponent_id) {
                $opponentRuns += $runs;
            }
        }

        if ((int) $battle->challenger_runs !== $challengerRuns || (int) $battle->opponent_runs !== $opponentRuns) {
            $battle->update([
                'challenger_runs' => $challengerRuns,
                'opponent_runs' => $opponentRuns,
            ]);
        }

        if ($scoreData['is_completed'] && $battle->status === SquadBattle::STATUS_LOCKED_LIVE) {
            return $this->settleBattle($battle, $challengerRuns, $opponentRuns);
        }

        return $battle->fresh(['challenger', 'opponent', 'winner', 'picks']);
    }

    /**
     * Sync all active/live Squad Battles for a given match ID (called by LifecycleCron & FinishedMatchController).
     */
    public function syncBattlesForMatch(string|int $matchId): void
    {
        $battles = SquadBattle::where('match_id', (string) $matchId)
            ->whereIn('status', [
                SquadBattle::STATUS_PENDING_ACCEPT,
                SquadBattle::STATUS_DRAFTING,
                SquadBattle::STATUS_LOCKED_LIVE,
            ])
            ->get();

        foreach ($battles as $battle) {
            try {
                $this->syncLiveAndFinalScores($battle);
            } catch (\Throwable $e) {
                Log::warning("SquadBattleService::syncBattlesForMatch failed for battle #{$battle->id}: " . $e->getMessage());
            }
        }
    }

    /**
     * Expire pending or incomplete draft battles once Match Start - 5 mins has passed (0 CricGems deducted).
     */
    public function expireIfIncomplete(SquadBattle $battle): SquadBattle
    {
        return DB::transaction(function () use ($battle) {
            $locked = SquadBattle::where('id', $battle->id)->lockForUpdate()->firstOrFail();

            if (
                in_array($locked->status, [SquadBattle::STATUS_PENDING_ACCEPT, SquadBattle::STATUS_DRAFTING], true)
                && !$locked->isBeforeLock()
            ) {
                $locked->update([
                    'status' => SquadBattle::STATUS_EXPIRED_INCOMPLETE,
                    'current_turn_user_id' => null,
                ]);
            }

            return $locked->fresh(['challenger', 'opponent', 'picks']);
        });
    }

    /**
     * Atomically settle a completed battle (`1 Run = 1 CricGem`), allowing negative balance on loser.
     */
    protected function settleBattle(SquadBattle $battle, int $challengerRuns, int $opponentRuns): SquadBattle
    {
        return DB::transaction(function () use ($battle, $challengerRuns, $opponentRuns) {
            $locked = SquadBattle::where('id', $battle->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === SquadBattle::STATUS_SETTLED) {
                return $locked->fresh(['challenger', 'opponent', 'winner', 'picks']);
            }

            $margin = abs($challengerRuns - $opponentRuns);
            $winnerId = null;
            $loserId = null;

            if ($challengerRuns > $opponentRuns) {
                $winnerId = (int) $locked->challenger_id;
                $loserId = (int) $locked->opponent_id;
            } elseif ($opponentRuns > $challengerRuns) {
                $winnerId = (int) $locked->opponent_id;
                $loserId = (int) $locked->challenger_id;
            }

            $challenger = User::where('id', $locked->challenger_id)->lockForUpdate()->firstOrFail();
            $opponent = User::where('id', $locked->opponent_id)->lockForUpdate()->firstOrFail();

            $challenger->increment('battles_played');
            $opponent->increment('battles_played');

            if ($margin > 0 && $winnerId !== null && $loserId !== null) {
                $winner = $winnerId === (int) $challenger->id ? $challenger : $opponent;
                $loser = $loserId === (int) $challenger->id ? $challenger : $opponent;

                $winner->increment('battles_won');

                $this->cricGemsService->credit(
                    $winner,
                    $margin,
                    'squad_battle_win',
                    "Won {$locked->match_title} ({$locked->modeLabel()}) by {$margin} runs",
                    SquadBattle::class,
                    $locked->id
                );

                $this->cricGemsService->debit(
                    $loser,
                    $margin,
                    'squad_battle_loss',
                    "Lost {$locked->match_title} ({$locked->modeLabel()}) by {$margin} runs",
                    SquadBattle::class,
                    $locked->id,
                    true // Allow negative balance on post-match settlement
                );
            }

            $locked->update([
                'challenger_runs' => $challengerRuns,
                'opponent_runs' => $opponentRuns,
                'run_margin' => $margin,
                'winner_id' => $winnerId,
                'status' => SquadBattle::STATUS_SETTLED,
                'settled_at' => now(),
            ]);

            $this->cricGemsService->grantReferralBonusWhenQualified($challenger->fresh());
            $this->cricGemsService->grantReferralBonusWhenQualified($opponent->fresh());

            $battleUrl = route('games.battle.show', $locked->slug);

            if ($winnerId !== null && $margin > 0) {
                $winnerUser = $winnerId === (int) $challenger->id ? $challenger : $opponent;
                $loserUser = $loserId === (int) $challenger->id ? $challenger : $opponent;

                UserNotification::create([
                    'user_id' => $winnerUser->id,
                    'type' => UserNotification::TYPE_BATTLE_RESULT,
                    'title' => "🏆 You won by {$margin} runs! (+{$margin} CricGems)",
                    'body' => "Congratulations Captain! Your squad defeated {$loserUser->captainTitle()} in {$locked->match_title}.",
                    'action_url' => $battleUrl,
                ]);

                UserNotification::create([
                    'user_id' => $loserUser->id,
                    'type' => UserNotification::TYPE_BATTLE_RESULT,
                    'title' => "⚔️ Battle Settled: {$locked->match_title}",
                    'body' => "{$winnerUser->captainTitle()} won by {$margin} runs (-{$margin} CricGems). View final scorecard & rematch!",
                    'action_url' => $battleUrl,
                ]);
            } else {
                foreach ([$challenger, $opponent] as $participant) {
                    UserNotification::create([
                        'user_id' => $participant->id,
                        'type' => UserNotification::TYPE_BATTLE_RESULT,
                        'title' => "🤝 Tied Battle: {$locked->match_title}",
                        'body' => "Both Captains finished tied on {$challengerRuns} runs! 0 CricGems transferred.",
                        'action_url' => $battleUrl,
                    ]);
                }
            }

            return $locked->fresh(['challenger', 'opponent', 'winner', 'picks']);
        });
    }

    /**
     * Load local scorecard and completion state from DB/Redis/Static JSON (0 external API calls).
     *
     * @return array{innings: array, batters: array, is_completed: bool}
     */
    protected function loadLocalScorecardPayload(string $matchId): array
    {
        $innings = [];
        $liveBatters = [];
        $isCompleted = false;

        $cms = CompletedMatchScorecard::where('match_id', $matchId)->first();
        if ($cms && is_array($cms->scorecard_payload) && !empty($cms->scorecard_payload)) {
            $innings = $cms->scorecard_payload['innings'] ?? (array_is_list($cms->scorecard_payload) ? $cms->scorecard_payload : []);
            $isCompleted = true;
        }

        $finished = FinishedMatch::where('match_id', $matchId)->first();
        if ($finished) {
            $isCompleted = true;
            if (empty($innings)) {
                $payload = is_array($finished->match_data) && !empty($finished->match_data)
                    ? $finished->match_data
                    : ($finished->match_json ?? []);
                if (!empty($payload['scorecard']) && is_array($payload['scorecard'])) {
                    $innings = $payload['scorecard'];
                }
            }
        }

        $livePayload = Cache::get("cricket:live_match_payload:{$matchId}")
            ?? Cache::get("cricket:redis:match:{$matchId}");

        if (!$livePayload) {
            $staticMatchPath = public_path("live-data/match-{$matchId}.json");
            if (file_exists($staticMatchPath)) {
                $decoded = json_decode(@file_get_contents($staticMatchPath), true);
                if (is_array($decoded)) {
                    $livePayload = $decoded;
                }
            }
        }

        if (is_array($livePayload)) {
            if (empty($innings) && !empty($livePayload['scorecard']) && is_array($livePayload['scorecard'])) {
                $innings = $livePayload['scorecard'];
            }
            if (!empty($livePayload['batters']) && is_array($livePayload['batters'])) {
                $liveBatters = $livePayload['batters'];
            } elseif (!empty($livePayload['live_batters']) && is_array($livePayload['live_batters'])) {
                $liveBatters = $livePayload['live_batters'];
            }

            if (!$isCompleted) {
                $isCompleted = MatchStateNormalizer::isFinished($livePayload)
                    || CricketLiveApiService::isCompletedMatch($livePayload);
            }
        }

        if (empty($innings)) {
            $cachedSc = Cache::get("live:match:{$matchId}:scorecard_16s");
            if (is_array($cachedSc)) {
                $innings = $cachedSc['innings'] ?? $cachedSc;
            }
        }

        if (!$isCompleted) {
            $managed = ManagedMatch::where('match_id', (int) $matchId)->first();
            if ($managed && ($managed->lifecycle_stage === 'completed' || strtolower((string) $managed->state) === 'finished')) {
                $isCompleted = true;
            }
        }

        return [
            'innings' => is_array($innings) ? $innings : [],
            'batters' => $liveBatters,
            'is_completed' => $isCompleted,
        ];
    }

    /**
     * Extract normalized player batting runs map from innings and live batters.
     *
     * @return array<string, array{name: string, runs: int, did_not_bat: bool}>
     */
    protected function extractBattingRunsFromPayload(array $scoreData): array
    {
        $map = [];

        foreach ($scoreData['innings'] ?? [] as $inning) {
            if (!is_array($inning)) {
                continue;
            }

            $batsmen = $inning['batsmen'] ?? $inning['batsman'] ?? $inning['batters'] ?? $inning['batting'] ?? [];
            if (!is_array($batsmen)) {
                continue;
            }

            foreach ($batsmen as $b) {
                if (!is_array($b)) {
                    continue;
                }
                $name = trim((string) ($b['name'] ?? $b['short_name'] ?? $b['batsman'] ?? ''));
                if ($name === '') {
                    continue;
                }

                $outDesc = strtolower(trim((string) ($b['out_desc'] ?? $b['out_by'] ?? $b['status'] ?? '')));
                $isExplicitDnb = str_contains($outDesc, 'did not bat') || $outDesc === 'dnb';
                $runs = (int) ($b['runs'] ?? $b['run'] ?? $b['r'] ?? 0);

                $norm = $this->normalizePlayerName($name);
                if ($norm === '') {
                    continue;
                }

                if (!isset($map[$norm])) {
                    $map[$norm] = [
                        'name' => $name,
                        'runs' => $isExplicitDnb ? 0 : $runs,
                        'did_not_bat' => $isExplicitDnb,
                    ];
                } elseif (!$isExplicitDnb) {
                    $map[$norm]['runs'] += $runs;
                    $map[$norm]['did_not_bat'] = false;
                }
            }
        }

        foreach ($scoreData['batters'] ?? [] as $lb) {
            if (!is_array($lb)) {
                continue;
            }
            $name = trim((string) ($lb['name'] ?? $lb['short_name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $norm = $this->normalizePlayerName($name);
            if ($norm === '') {
                continue;
            }
            $runs = (int) ($lb['runs'] ?? $lb['run'] ?? 0);
            if (!isset($map[$norm])) {
                $map[$norm] = [
                    'name' => $name,
                    'runs' => $runs,
                    'did_not_bat' => false,
                ];
            } elseif ($runs > $map[$norm]['runs']) {
                $map[$norm]['runs'] = $runs;
                $map[$norm]['did_not_bat'] = false;
            }
        }

        return $map;
    }

    /**
     * Match a picked player name against extracted batting runs (exact or unambiguous initial+surname match).
     *
     * @return array{name: string, runs: int, did_not_bat: bool}|null
     */
    protected function findBatterRuns(string $pickName, array $battingRunsMap): ?array
    {
        $normPick = $this->normalizePlayerName($pickName);
        if ($normPick === '') {
            return null;
        }

        if (isset($battingRunsMap[$normPick])) {
            return $battingRunsMap[$normPick];
        }

        $pickParts = explode(' ', $normPick);
        $pickFirst = $pickParts[0] ?? '';
        $pickLast = end($pickParts) ?: '';

        $candidates = [];
        foreach ($battingRunsMap as $normKey => $entry) {
            $keyParts = explode(' ', $normKey);
            $keyFirst = $keyParts[0] ?? '';
            $keyLast = end($keyParts) ?: '';

            if ($pickLast !== '' && $pickLast === $keyLast) {
                if ($pickFirst !== '' && $keyFirst !== '' && substr($pickFirst, 0, 1) === substr($keyFirst, 0, 1)) {
                    $candidates[] = $entry;
                }
            }
        }

        if (count($candidates) === 1) {
            return $candidates[0];
        }

        return null;
    }

    /**
     * Normalize squad player arrays into a consistent structure with Playing XI flags.
     */
    protected function normalizeSquadList(array $rawPlayers, string $teamName, array $xiNameMap, bool $playingXiAnnounced): array
    {
        $normalized = [];
        $seen = [];

        foreach ($rawPlayers as $item) {
            if (is_string($item)) {
                $name = trim($item);
                $playerArr = ['name' => $name];
            } elseif (is_array($item)) {
                $name = trim((string) ($item['name'] ?? $item['player_name'] ?? $item['full_name'] ?? ''));
                $playerArr = $item;
            } else {
                continue;
            }

            if ($name === '') {
                continue;
            }

            $normName = $this->normalizePlayerName($name);
            if ($normName === '' || isset($seen[$normName])) {
                continue;
            }
            $seen[$normName] = true;

            $role = trim((string) ($playerArr['play_role'] ?? $playerArr['role'] ?? $playerArr['playing_role'] ?? $playerArr['role_desc'] ?? ''));
            $isPlaying = $playingXiAnnounced && isset($xiNameMap[$normName]);

            $normalized[] = [
                'player_id' => (string) ($playerArr['player_id'] ?? $playerArr['id'] ?? ''),
                'name' => $name,
                'norm_name' => $normName,
                'team' => $teamName,
                'role' => $this->shortenRole($role),
                'role_full' => $role !== '' ? $role : 'Player',
                'image' => (string) ($playerArr['image'] ?? $playerArr['photo'] ?? ''),
                'is_playing_xi' => $isPlaying,
                'is_captain' => !empty($playerArr['is_captain']) || str_contains(strtoupper($role), '(C)'),
                'is_keeper' => !empty($playerArr['is_keeper']) || str_contains(strtoupper($role), '(WK)'),
            ];
        }

        // Sort Playing XI first once announced
        if ($playingXiAnnounced) {
            usort($normalized, fn (array $a, array $b) => ($b['is_playing_xi'] <=> $a['is_playing_xi']));
        }

        return $normalized;
    }

    protected function syncPicksPlayingXiStatus(SquadBattle $battle, array $teamAPlayers, array $teamBPlayers): void
    {
        $playingMap = [];
        foreach (array_merge($teamAPlayers, $teamBPlayers) as $p) {
            $playingMap[$p['norm_name']] = (bool) $p['is_playing_xi'];
        }

        foreach ($battle->picks as $pick) {
            $norm = $this->normalizePlayerName($pick->player_name);
            if (array_key_exists($norm, $playingMap)) {
                $isPlaying = $playingMap[$norm];
                if ((bool) $pick->is_playing_xi !== $isPlaying) {
                    $pick->update(['is_playing_xi' => $isPlaying]);
                }
            }
        }
    }

    protected function extractPlayerNameMap(array $players): array
    {
        $map = [];
        foreach ($players as $p) {
            $name = is_array($p)
                ? trim((string) ($p['name'] ?? $p['player_name'] ?? $p['full_name'] ?? ''))
                : trim((string) $p);
            $norm = $this->normalizePlayerName($name);
            if ($norm !== '') {
                $map[$norm] = true;
            }
        }

        return $map;
    }

    protected function shortenRole(string $role): string
    {
        $lower = strtolower($role);
        if ($lower === '') {
            return 'PLAYER';
        }
        if (str_contains($lower, 'wk') || str_contains($lower, 'keeper')) {
            return 'WK';
        }
        if (str_contains($lower, 'all')) {
            return 'ALL';
        }
        if (str_contains($lower, 'bowl')) {
            return 'BOWL';
        }
        if (str_contains($lower, 'bat')) {
            return 'BAT';
        }

        return strtoupper(mb_substr($role, 0, 8));
    }

    public function normalizePlayerName(string $name): string
    {
        $clean = preg_replace('/\((c|wk|vc)\)/i', '', $name);
        $clean = str_replace(['*', '.', ','], ' ', (string) $clean);
        $clean = preg_replace('/\s+/', ' ', trim(mb_strtolower((string) $clean)));

        return (string) $clean;
    }

    /**
     * Strictly enforce T20 & ODI formats only (reject Test / First-Class / unknown).
     */
    public function normalizeAllowedFormat(array $match): ?string
    {
        $rawFormat = strtoupper(trim((string) (
            $match['format']
            ?? $match['match_format']
            ?? $match['raw']['match_format']
            ?? $match['raw']['format']
            ?? $match['match_type']
            ?? ''
        )));

        $desc = strtolower(trim((string) ($match['match_desc'] ?? $match['matchs'] ?? '')));

        if (
            $rawFormat === ''
            || str_contains(strtolower($rawFormat), 'test')
            || str_contains(strtolower($rawFormat), 'first')
            || str_contains($rawFormat, 'FC')
            || preg_match('/\b(test|first[- ]class|4[- ]day|3[- ]day)\b/i', $desc)
        ) {
            return null;
        }

        if (in_array($rawFormat, ['T20', 'T20I', 'T10'], true)) {
            return $rawFormat === 'T10' ? 'T10' : 'T20';
        }

        if ($rawFormat === 'ODI') {
            return 'ODI';
        }

        return null;
    }

    protected function resolveStartTimeIst(array $match): ?Carbon
    {
        $rawIst = $match['start_time_ist'] ?? null;
        if (!empty($rawIst) && is_string($rawIst)) {
            try {
                return Carbon::parse($rawIst, 'Asia/Kolkata');
            } catch (\Throwable) {
            }
        }

        $parsed = $this->apiService->parseMatchStartToIst($match);
        if ($parsed !== null) {
            return $parsed;
        }

        foreach (['start_time', 'match_date', 'date'] as $key) {
            if (!empty($match[$key]) && is_string($match[$key])) {
                try {
                    return Carbon::parse($match[$key])->timezone('Asia/Kolkata');
                } catch (\Throwable) {
                }
            }
        }

        return null;
    }

    protected function generateUniqueSlug(string $cName, string $oName, string $teamA, string $teamB, string $format): string
    {
        do {
            $suffix = random_int(100, 999);
            $slug = Str::slug("{$cName}-vs-{$oName}-{$teamA}-vs-{$teamB}-{$format}-battle-{$suffix}");
            if ($slug === '') {
                $slug = 'captain-battle-' . random_int(1000, 9999);
            }
            $slug = mb_substr($slug, 0, 175);
        } while (SquadBattle::where('slug', $slug)->exists());

        return $slug;
    }
}
