<x-mail::message>
<div style="text-align: center; margin-bottom: 10px;">
<img src="{{ $appUrl }}/logo.png" width="80" alt="Most AI Mentions" style="display: inline-block;" />
</div>

<div style="text-align: center; margin-bottom: 8px;">
<span style="font-size: 12px; text-transform: uppercase; letter-spacing: 2px; color: #9ca3af;">Weekly Awards</span>
</div>

# This Week's Most AI-Hyped Sites

<div style="text-align: center; margin-bottom: 24px; color: #6b7280;">
{{ $weekStart->format('M j') }} – {{ $weekEnd->format('M j, Y') }}
</div>

@foreach ($topSites as $index => $site)
@php
$rank = $index + 1;
$medal = match($rank) { 1 => '🥇', 2 => '🥈', 3 => '🥉', default => "#{$rank}" };
$siteUrl = "{$appUrl}/sites/{$site['slug']}";
$screenshotUrl = $site['screenshot_url'] ?? null;
@endphp
<div style="border: 1px solid #e5e7eb; border-radius: 8px; margin-bottom: 16px; overflow: hidden;">
@if ($screenshotUrl)
<a href="{{ $siteUrl }}"><img src="{{ $screenshotUrl }}" width="100%" alt="{{ $site['name'] }}" style="display: block; max-height: 200px; object-fit: cover;" /></a>
@endif
<div style="padding: 16px;">
<div style="display: flex; justify-content: space-between; align-items: center;">
<div>
<span style="font-size: 20px; font-weight: bold;">{{ $medal }}</span>
<a href="{{ $siteUrl }}" style="font-size: 18px; font-weight: bold; color: #111827; text-decoration: none;">{{ $site['name'] }}</a>
<span style="color: #9ca3af; font-size: 14px;"> · {{ $site['domain'] }}</span>
</div>
</div>
<div style="margin-top: 8px;">
<span style="display: inline-block; background: #fef3c7; color: #92400e; font-weight: bold; font-size: 14px; padding: 4px 12px; border-radius: 999px;">Hype Score: {{ number_format($site['hype_score']) }}</span>
</div>
</div>
</div>
@endforeach

<x-mail::button :url="$appUrl">
View Full Leaderboard
</x-mail::button>

Thanks for tracking the hype,<br>
{{ config('app.name') }}

<x-mail::subcopy>
[Unsubscribe]({{ $unsubscribeUrl }}) from future newsletters.
</x-mail::subcopy>
</x-mail::message>
