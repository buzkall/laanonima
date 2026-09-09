{{-- Every face on one page, for the one check no code can make.

     Written to storage/app/private by `cupida:portraits:resolve --sheet` and
     opened from the filesystem: no route, no controller, no auth, nothing that
     ships. The occupation guard keeps the wrong *person* off a card, but it
     cannot tell that a correctly identified writer's only photograph is a
     statue, a book cover or a group shot at a festival, and it cannot know that
     a byline is three people sharing a pen name. A person can, in about a
     second per tile, which is the whole reason this file exists.

     Images are referenced by absolute path so the page works wherever it is
     opened from. --}}
@php
    $of = fn(string $status) => collect($portraits)->where('status', $status)->sortBy('name');

    $matched = $of('matched');
    $missing = collect($portraits)
        ->whereIn('status', ['no_image', 'no_match'])
        ->sortBy('name');
    $notPeople = $of('no_person');
@endphp
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8" />
    <title>Retratos de La Cupida</title>
    <style>
        body {
            margin: 0;
            padding: 32px;
            background: #f6f3ee;
            color: #211511;
            font:
                14px/1.4 ui-sans-serif,
                system-ui,
                sans-serif;
        }
        h1,
        h2 {
            font-weight: 600;
        }
        h1 {
            margin: 0 0 4px;
            font-size: 22px;
        }
        .count {
            margin: 0 0 28px;
            color: #6b625c;
        }
        .grid {
            display: grid;
            gap: 20px;
            grid-template-columns: repeat(auto-fill, minmax(190px, 1fr));
        }
        .tile {
            background: #fff;
            border-radius: 6px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(33, 21, 17, 0.12);
        }
        .tile img {
            display: block;
            width: 100%;
            aspect-ratio: 3 / 4;
            object-fit: cover;
            background: #ddd;
        }
        .tile .body {
            padding: 10px 12px 12px;
        }
        .name {
            font-weight: 600;
        }
        .who {
            margin: 2px 0 0;
            color: #6b625c;
            font-size: 12px;
        }
        .who a {
            color: #6b625c;
        }
        .fix {
            display: block;
            width: 100%;
            margin-top: 8px;
            padding: 4px 6px;
            border: 1px solid #ddd6cd;
            border-radius: 4px;
            background: #faf8f5;
            font:
                11px/1.3 ui-monospace,
                monospace;
            color: #6b625c;
        }
        ul {
            padding-left: 18px;
        }
        li {
            margin-bottom: 8px;
        }
        code {
            background: #eee9e2;
            padding: 1px 5px;
            border-radius: 3px;
            font:
                11px/1.3 ui-monospace,
                monospace;
        }
        .missing {
            max-width: 760px;
        }
    </style>
</head>
<body>
    <h1>Retratos de La Cupida</h1>
    <p class="count">
        {{ $matched->count() }} con foto · {{ $missing->count() }} sin foto · {{ $notPeople->count() }} no son personas
    </p>

    {{-- The label and description are the point of the tile, not decoration:
     "Stephen King · político irlandés" is a wrong match spotted at a glance,
     and a bare QID is not. --}}
    <div class="grid">
        @foreach ($matched as $portrait)
            <div class="tile">
                <img src="file://{{ $disk }}/{{ $portrait['photo'] }}" alt="{{ $portrait['name'] }}" />
                <div class="body">
                    <div class="name">{{ $portrait['name'] }}</div>
                    <p class="who">
                        {{ data_get($portrait, 'wikidata.description') ?: '—' }}
                        @if ($portrait['qid'])
                            ·
                            <a href="https://www.wikidata.org/wiki/{{ $portrait['qid'] }}">{{ $portrait['qid'] }}</a>
                        @endif
                        @if ($portrait['pinned'] ?? false)
                            · fijado a mano
                        @endif
                    </p>
                    <input
                        class="fix"
                        readonly
                        value="php artisan cupida:portraits:resolve --only={{ $portrait['slug'] }} --qid="
                    />
                </div>
            </div>
        @endforeach
    </div>

    @if ($missing->isNotEmpty())
        <h2>Sin foto ({{ $missing->count() }})</h2>
        <div class="missing">
            <ul>
                @foreach ($missing as $portrait)
                    <li>
                        <strong>{{ $portrait['name'] }}</strong>
                        @if (data_get($portrait, 'wikidata.description'))
                            — {{ data_get($portrait, 'wikidata.description') }}
                        @endif
                        <br />
                        <code>php artisan cupida:portraits:resolve --only={{ $portrait['slug'] }} --qid=</code>
                        <code>--none</code>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    @if ($notPeople->isNotEmpty())
        <h2>No son personas ({{ $notPeople->count() }})</h2>
        <div class="missing">
            <ul>
                @foreach ($notPeople as $portrait)
                    <li>{{ $portrait['name'] }} <code>{{ $portrait['slug'] }}</code></li>
                @endforeach
            </ul>
        </div>
    @endif
</body>
</html>
