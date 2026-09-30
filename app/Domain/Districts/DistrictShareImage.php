<?php

namespace App\Domain\Districts;

use App\Models\Electorate;
use App\Models\Membership;
use GdImage;

/**
 * The picture shown when a district page is shared: the district, its MLA and
 * its region's MLCs with their parties, and nothing else. No figures and no
 * ranking, so it says the same thing for every district and favours no party.
 *
 * The picture is drawn on demand with GD and cached for a year at an address
 * that contains a hash of everything drawn, so a change (a new member, a
 * party's colour, the authorisation statement) gets a new address.
 *
 * @phpstan-type Person array{name: string, party: string, colour: string}
 * @phpstan-type Card array{district: string, region: string, vacant: bool, member: ?Person, council: list<Person>}
 */
class DistrictShareImage
{
    /** Bump this when the design or the fonts change, so pictures cached before it are replaced. */
    public const DESIGN_VERSION = 1;

    public const WIDTH = 1200;

    public const HEIGHT = 630;

    /** The colours in the site's party stripe, in its order. They match the tokens in resources/css/app.css. */
    public const STRIPE = [
        'ajp' => '#8c2c8c',
        'dlp' => '#7e1e2b',
        'ffv' => '#1b9aaa',
        'grn' => '#10c25b',
        'alp' => '#de3533',
        'lcv' => '#2f7d32',
        'lib' => '#1c4f9c',
        'lbt' => '#e0b000',
        'nat' => '#006644',
        'onp' => '#f36c21',
        'sff' => '#7a5c3e',
    ];

    private const GREY = '#737373';

    private const INK = '#111111';

    private const MUTED = '#525252';

    private const RULE = '#e5e5e5';

    private const PAD = 72;

    /**
     * What the picture shows for a district, from the database.
     *
     * @return Card
     */
    public function cardFor(Electorate $district): array
    {
        $region = $district->region;

        $seats = Membership::query()
            ->current()
            ->whereIn('electorate_id', array_filter([$district->id, $region?->id]))
            ->with(['member', 'party'])
            ->get()
            ->sortBy(fn (Membership $seat): string => $seat->member->last_name.' '.$seat->member->first_name);

        return $this->cardFromSeats(
            $district,
            $region,
            $seats->firstWhere('electorate_id', $district->id),
            $seats->where('electorate_id', $region?->id),
        );
    }

    /**
     * What the picture shows, from seats already loaded (with their member and
     * party), in the order they should be listed. The district page uses this
     * to work out the picture's address without another query.
     *
     * @param  iterable<Membership>  $councillors
     * @return Card
     */
    public function cardFromSeats(Electorate $district, ?Electorate $region, ?Membership $mla, iterable $councillors): array
    {
        $council = [];

        foreach ($councillors as $seat) {
            $council[] = $this->person($seat);
        }

        return [
            'district' => $district->name,
            'region' => $region->name ?? '',
            'vacant' => $mla === null,
            'member' => $mla === null ? null : $this->person($mla),
            'council' => $council,
        ];
    }

    /**
     * A short fingerprint of everything drawn, for the picture's address.
     *
     * @param  Card  $card
     */
    public function hash(array $card): string
    {
        return substr(hash('sha256', json_encode([
            'card' => $card,
            'authorisation' => $this->authorisation(),
            'host' => parse_url((string) config('app.url'), PHP_URL_HOST),
            'design' => self::DESIGN_VERSION,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)), 0, 16);
    }

    /**
     * @param  Card  $card
     */
    public function url(Electorate $district, array $card): string
    {
        return route('districts.share', ['district' => $district->slug, 'hash' => $this->hash($card)]);
    }

    /**
     * What the picture shows, in words, for people who can't see it.
     *
     * @param  Card  $card
     */
    public function alt(array $card): string
    {
        $mla = $card['member'] === null ? 'the seat is vacant' : "{$card['member']['name']} ({$card['member']['party']})";
        $count = count($card['council']);
        $council = $count === 0
            ? ''
            : ", and {$count} ".($count === 1 ? 'member' : 'members')." of the Legislative Council for {$card['region']} Region";

        return "{$card['district']} District: {$mla}{$council}.";
    }

    /**
     * The picture, as PNG bytes.
     *
     * @param  Card  $card
     */
    public function render(array $card): string
    {
        $image = imagecreatetruecolor(self::WIDTH, self::HEIGHT);
        imagefilledrectangle($image, 0, 0, self::WIDTH, self::HEIGHT, $this->colour($image, '#ffffff'));

        $fonts = [
            'regular' => resource_path('fonts/Inter-Regular.ttf'),
            'medium' => resource_path('fonts/Inter-Medium.ttf'),
            'semibold' => resource_path('fonts/Inter-SemiBold.ttf'),
        ];

        $footerTop = $this->drawFooter($image, $fonts);
        $this->drawHeader($image, $fonts);

        $bodyTop = 100;
        $bodyBottom = $footerTop - 14;

        $this->drawTitle($image, $fonts, $card, $bodyTop, $bodyBottom);
        $this->drawMembers($image, $fonts, $card, $bodyTop, $bodyBottom);

        ob_start();
        imagepng($image, null, 6);

        return (string) ob_get_clean();
    }

    /**
     * @return Person
     */
    private function person(Membership $seat): array
    {
        return [
            'name' => $seat->member->display_name,
            'party' => $seat->party->display_name ?? $seat->party->name,
            'colour' => $seat->party->colour ?: self::GREY,
        ];
    }

    private function authorisation(): string
    {
        return (string) config('site.authorisation');
    }

    /**
     * @param  array<string, string>  $fonts
     */
    private function drawHeader(GdImage $image, array $fonts): void
    {
        $title = 'Do They Represent Me?';

        $this->text($image, $fonts['semibold'], 28, self::PAD, 76, self::INK, $title);
        $this->text($image, $fonts['medium'], 20, self::PAD + $this->width($fonts['semibold'], 28, $title) + 14, 76, self::MUTED, 'VICTORIA 2026');
        $this->text($image, $fonts['medium'], 20, self::WIDTH - self::PAD, 76, self::MUTED, 'YOUR DISTRICT', 'right');
    }

    /**
     * Draws the footer from the bottom up and returns where it starts.
     *
     * @param  array<string, string>  $fonts
     */
    private function drawFooter(GdImage $image, array $fonts): int
    {
        $inner = self::WIDTH - self::PAD * 2;
        $stripeTop = self::HEIGHT - 12;
        $segment = self::WIDTH / count(self::STRIPE);

        foreach (array_values(self::STRIPE) as $index => $hex) {
            imagefilledrectangle($image, (int) round($index * $segment), $stripeTop, (int) round(($index + 1) * $segment) - 1, self::HEIGHT - 1, $this->colour($image, $hex));
        }

        $bottom = $stripeTop - 26;
        $statement = $this->authorisation();

        if ($statement !== '') {
            $lines = $this->wrap($fonts['regular'], 15, $inner, $statement);
            $top = $bottom - count($lines) * 20;

            foreach ($lines as $index => $line) {
                $this->text($image, $fonts['regular'], 15, self::PAD, $top + $index * 20 + 15, self::MUTED, $line);
            }

            $bottom = $top - 8;
        }

        $host = (string) parse_url((string) config('app.url'), PHP_URL_HOST);
        $independent = 'Independent. Not affiliated with any party, candidate or the Parliament.';
        $sideBySide = $this->width($fonts['semibold'], 22, $host) + $this->width($fonts['regular'], 17, $independent) + 40 <= $inner;

        $hostBaseline = $bottom - 4;

        if (! $sideBySide) {
            $this->text($image, $fonts['regular'], 17, self::PAD, $hostBaseline, self::MUTED, $independent);
            $hostBaseline -= 26;
        }

        $this->text($image, $fonts['semibold'], 22, self::PAD, $hostBaseline, self::INK, $host);

        if ($sideBySide) {
            $this->text($image, $fonts['regular'], 17, self::WIDTH - self::PAD, $hostBaseline, self::MUTED, $independent, 'right');
        }

        return $hostBaseline - 28;
    }

    /**
     * @param  array<string, string>  $fonts
     * @param  Card  $card
     */
    private function drawTitle(GdImage $image, array $fonts, array $card, int $top, int $bottom): void
    {
        $width = 470;
        $available = $bottom - $top;
        $title = "{$card['district']} District";
        $longestWord = collect(explode(' ', $title))->sortByDesc(fn (string $word): int => mb_strlen($word))->first() ?? $title;
        $subtitle = $card['region'] === '' ? '' : "{$card['region']} Region. Members of the 60th Parliament, and how they voted.";

        // A long name gets a smaller title, and in the last resort no subtitle, so it never runs into the footer.
        $size = $this->fit($fonts['semibold'], 72, 36, $width, $longestWord);

        do {
            $lineHeight = (int) round($size * 76 / 72);
            $titleLines = $this->wrap($fonts['semibold'], $size, $width, $title);
            $subtitleLines = $subtitle === '' ? [] : $this->wrap($fonts['regular'], 26, $width, $subtitle);
            $height = 3 + 24 + count($titleLines) * $lineHeight + ($subtitleLines === [] ? 0 : 24 + count($subtitleLines) * 34);

            if ($height > $available && $subtitleLines !== [] && $size <= 44) {
                $subtitle = '';
                $height = 3 + 24 + count($titleLines) * $lineHeight;
            }

            $fits = $height <= $available;
            $size -= $fits ? 0 : 4;
        } while (! $fits && $size >= 36);

        $y = (int) round($top + max(0, ($available - $height) / 2));

        imagefilledrectangle($image, self::PAD, $y, self::PAD + 95, $y + 2, $this->colour($image, self::INK));
        $y += 3 + 24;

        foreach ($titleLines as $line) {
            $this->text($image, $fonts['semibold'], $size, self::PAD, $y + (int) round($size * 0.78), self::INK, $line);
            $y += $lineHeight;
        }

        $y += 24;

        foreach ($subtitleLines as $line) {
            $this->text($image, $fonts['regular'], 26, self::PAD, $y + 26, self::MUTED, $line);
            $y += 34;
        }
    }

    /**
     * @param  array<string, string>  $fonts
     * @param  Card  $card
     */
    private function drawMembers(GdImage $image, array $fonts, array $card, int $top, int $bottom): void
    {
        $x = 598;
        $width = self::WIDTH - self::PAD - $x;

        $mla = $card['member'] ?? ['name' => 'No member at present', 'party' => 'Seat vacant', 'colour' => '#cfcfcf'];
        $mlaRow = $this->personLayout($fonts, $mla, 26, 20, $width, 48);
        $councilHeight = $card['council'] === [] ? 46 : array_sum(array_map(fn (array $person): int => $this->personLayout($fonts, $person, 24, 20, $width, 46)['height'], $card['council']));
        $height = 30 + $mlaRow['height'] + 22 + 30 + $councilHeight;

        $y = (int) round($top + max(0, ($bottom - $top - $height) / 2));

        $y = $this->drawHeading($image, $fonts, $x, $y, $width, "Member for {$card['district']} · Legislative Assembly");
        $y = $this->drawPerson($image, $fonts, $mla, $x, $y, $width, 26, 20, 48);
        $y += 22;

        $heading = $card['region'] === '' ? 'Legislative Council' : "Legislative Council · {$card['region']}";
        $y = $this->drawHeading($image, $fonts, $x, $y, $width, $heading);

        if ($card['council'] === []) {
            $this->text($image, $fonts['regular'], 20, $x, $y + 30, self::MUTED, 'No members are recorded for this region.');

            return;
        }

        foreach ($card['council'] as $person) {
            $y = $this->drawPerson($image, $fonts, $person, $x, $y, $width, 24, 20, 46);
        }
    }

    /**
     * A small heading in capitals, with a heavy rule under it. Returns the next y.
     *
     * @param  array<string, string>  $fonts
     */
    private function drawHeading(GdImage $image, array $fonts, int $x, int $y, int $width, string $text): int
    {
        $text = mb_strtoupper($text);
        $size = $this->fit($fonts['medium'], 15, 11, $width, $text);

        $this->text($image, $fonts['medium'], $size, $x, $y + 15, self::MUTED, $text);
        imagefilledrectangle($image, $x, $y + 23, $x + $width - 1, $y + 24, $this->colour($image, self::INK));

        return $y + 30;
    }

    /**
     * How tall a person's row is. A name and party that don't fit side by side stack.
     *
     * @param  array<string, string>  $fonts
     * @param  Person  $person
     * @return array{stacked: bool, height: int}
     */
    private function personLayout(array $fonts, array $person, float $nameSize, float $partySize, int $width, int $rowHeight): array
    {
        $room = $width - 28;
        $stacked = $this->width($fonts['medium'], $nameSize, $person['name']) + $this->width($fonts['regular'], $partySize, $person['party']) + 24 > $room;

        return ['stacked' => $stacked, 'height' => $stacked ? $rowHeight + 26 : $rowHeight];
    }

    /**
     * One member: a swatch in their party's colour, their name, and their party. Returns the next y.
     *
     * @param  array<string, string>  $fonts
     * @param  Person  $person
     */
    private function drawPerson(GdImage $image, array $fonts, array $person, int $x, int $y, int $width, float $nameSize, float $partySize, int $rowHeight): int
    {
        $layout = $this->personLayout($fonts, $person, $nameSize, $partySize, $width, $rowHeight);
        $room = $width - 28;
        $size = $this->fit($fonts['medium'], $nameSize, 16, $room, $person['name']);

        imagefilledrectangle($image, $x, $y + (int) round(($rowHeight - 14) / 2), $x + 13, $y + (int) round(($rowHeight - 14) / 2) + 13, $this->colour($image, $person['colour']));

        if ($layout['stacked']) {
            $this->text($image, $fonts['medium'], $size, $x + 28, $y + (int) round($rowHeight / 2 + $size * 0.36), self::INK, $person['name']);
            $this->text($image, $fonts['regular'], 18, $x + 28, $y + $rowHeight + 10, self::MUTED, $person['party']);
        } else {
            $this->text($image, $fonts['medium'], $size, $x + 28, $y + (int) round($rowHeight / 2 + $size * 0.36), self::INK, $person['name']);
            $this->text($image, $fonts['regular'], $partySize, $x + $width, $y + (int) round($rowHeight / 2 + $partySize * 0.36), self::MUTED, $person['party'], 'right');
        }

        $bottom = $y + $layout['height'];
        imagefilledrectangle($image, $x, $bottom - 1, $x + $width - 1, $bottom - 1, $this->colour($image, self::RULE));

        return $bottom;
    }

    /**
     * Draws a line of text with its baseline at $baseline. GD sizes text in
     * points at 96 dpi, so a size in pixels is scaled by 0.75.
     */
    private function text(GdImage $image, string $font, float $size, int $x, int $baseline, string $colour, string $text, string $align = 'left'): void
    {
        $left = $align === 'right' ? $x - $this->width($font, $size, $text) : $x;

        imagettftext($image, $size * 0.75, 0, $left, $baseline, $this->colour($image, $colour), $font, $text);
    }

    private function width(string $font, float $size, string $text): int
    {
        $box = imagettfbbox($size * 0.75, 0, $font, $text);

        return $box === false ? 0 : $box[2] - $box[0];
    }

    /**
     * The largest size, down to a minimum, at which the text fits. A name too
     * wide at the minimum is left to overflow rather than be cut short.
     */
    private function fit(string $font, float $size, float $minimum, int $width, string $text): float
    {
        while ($size > $minimum && $this->width($font, $size, $text) > $width) {
            $size -= 1;
        }

        return $size;
    }

    /**
     * @return list<string>
     */
    private function wrap(string $font, float $size, int $width, string $text): array
    {
        $lines = [];
        $line = '';

        foreach (preg_split('/\s+/', trim($text)) ?: [] as $word) {
            $attempt = $line === '' ? $word : "{$line} {$word}";

            if ($line !== '' && $this->width($font, $size, $attempt) > $width) {
                $lines[] = $line;
                $line = $word;
            } else {
                $line = $attempt;
            }
        }

        if ($line !== '') {
            $lines[] = $line;
        }

        return $lines;
    }

    private function colour(GdImage $image, string $hex): int
    {
        [$red, $green, $blue] = sscanf(ltrim($hex, '#'), '%02x%02x%02x');

        return (int) imagecolorallocate($image, $red, $green, $blue);
    }
}
