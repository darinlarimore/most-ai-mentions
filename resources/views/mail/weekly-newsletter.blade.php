<x-mail::message>
# AI Hype Leaderboard

Here are the top {{ count($topSites) }} most AI-hyped sites for the week of {{ $weekStart->format('M j') }} – {{ $weekEnd->format('M j, Y') }}:

<x-mail::table>
| Rank | Site | Hype Score |
|:-----|:-----|:-----------|
@foreach ($topSites as $index => $site)
| {{ $index + 1 }} | [{{ $site['name'] }}]({{ $site['url'] }}) | {{ $site['hype_score'] }} |
@endforeach
</x-mail::table>

<x-mail::button :url="config('app.url')">
View Full Leaderboard
</x-mail::button>

Thanks for tracking the hype,<br>
{{ config('app.name') }}

<x-mail::subcopy>
[Unsubscribe]({{ $unsubscribeUrl }}) from future newsletters.
</x-mail::subcopy>
</x-mail::message>
