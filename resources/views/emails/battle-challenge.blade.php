<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cricwire Captain Battle Challenge</title>
</head>
<body style="margin:0; padding:0; background-color:#020617; font-family:-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; color:#f8fafc;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background-color:#020617; padding:32px 16px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:560px; background-color:#0f172a; border:1px solid #1e293b; border-radius:20px; overflow:hidden; box-shadow:0 20px 40px rgba(0,0,0,0.45);">
                    <!-- Header -->
                    <tr>
                        <td style="padding:24px 28px; background:linear-gradient(135deg, #0f172a 0%, #1e293b 100%); border-bottom:1px solid #334155;">
                            <img src="{{ asset('images/cricwire-logo.png') }}" alt="Cricwire" height="38" style="display:block; height:38px; width:auto; border:0; margin-bottom:10px;">
                            <div style="font-size:11px; font-weight:800; letter-spacing:2px; text-transform:uppercase; color:#fbbf24;">
                                ⚔️ CAPTAIN ARENA CHALLENGE
                            </div>
                            <h1 style="margin:6px 0 0; font-size:22px; font-weight:900; color:#ffffff; line-height:1.3;">
                                You've Been Challenged!
                            </h1>
                        </td>
                    </tr>

                    <!-- Body -->
                    <tr>
                        <td style="padding:28px;">
                            <p style="margin:0 0 16px; font-size:15px; line-height:1.6; color:#cbd5e1;">
                                Hey <strong style="color:#ffffff;">{{ $opponentName }}</strong>,
                            </p>
                            <p style="margin:0 0 22px; font-size:15px; line-height:1.6; color:#cbd5e1;">
                                <strong style="color:#fbbf24;">{{ $challengerName }}</strong> has challenged you to a <strong style="color:#34d399;">{{ $battle->modeLabel() }} Squad Runs Battle</strong> on Cricwire!
                            </p>

                            <!-- Match Details Box -->
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background-color:#020617; border:1px solid #334155; border-radius:14px; margin-bottom:24px;">
                                <tr>
                                    <td style="padding:18px 20px;">
                                        <div style="font-size:11px; font-weight:800; text-transform:uppercase; letter-spacing:1px; color:#94a3b8; margin-bottom:6px;">
                                            Match &amp; Format
                                        </div>
                                        <div style="font-size:17px; font-weight:900; color:#ffffff; margin-bottom:10px;">
                                            {{ $battle->match_title }}
                                        </div>
                                        <div style="font-size:13px; color:#cbd5e1; line-height:1.7;">
                                            <span style="display:inline-block; background:#1e293b; color:#fbbf24; padding:2px 8px; border-radius:6px; font-weight:800; font-size:11px; margin-right:6px;">{{ $battle->match_format }}</span>
                                            <span style="display:inline-block; background:#064e3b; color:#34d399; padding:2px 8px; border-radius:6px; font-weight:800; font-size:11px;">{{ $battle->modeLabel() }} ({{ $battle->team_size }} Picks Each)</span>
                                        </div>
                                        <div style="margin-top:12px; font-size:12px; color:#94a3b8;">
                                            ⏰ Draft Locks At: <strong style="color:#f8fafc;">{{ $locksAtIst }}</strong><br>
                                            💎 Scoring: <strong style="color:#34d399;">1 Batting Run = 1 CricGem</strong>
                                        </div>
                                    </td>
                                </tr>
                            </table>

                            <!-- CTA -->
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
                                <tr>
                                    <td align="center">
                                        <a href="{{ $battleUrl }}" style="display:inline-block; background:linear-gradient(90deg, #f59e0b 0%, #fbbf24 100%); color:#020617; font-size:14px; font-weight:900; text-decoration:none; padding:14px 28px; border-radius:12px; letter-spacing:0.5px; text-transform:uppercase;">
                                            ⚔️ Accept &amp; Join Live Draft
                                        </a>
                                    </td>
                                </tr>
                            </table>

                            <p style="margin:22px 0 0; font-size:12px; color:#64748b; text-align:center; line-height:1.5;">
                                Draft is live turn-by-turn with coin toss! Complete your picks before the 5-minute pre-match lock.
                            </p>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="padding:16px 28px; background-color:#020617; border-top:1px solid #1e293b; text-align:center; font-size:11px; color:#64748b;">
                            Cricwire Captain Arena • <a href="https://cricwire.in/games" style="color:#fbbf24; text-decoration:none;">cricwire.in/games</a>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
