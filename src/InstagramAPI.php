<?php

declare(strict_types=1);

namespace GetSocialAPI\Instagram;

/**
 * Instagram endpoints of GetSocialAPI.
 *
 * Methods map 1:1 to API routes and return the raw JSON responses as received.
 */
final class InstagramAPI
{
    public function __construct(private readonly TransportInterface $transport)
    {
    }

    // ---------- Profile ----------

    /** GET /ig/user/info/{user_id} - profile by numeric id. */
    public function userInfo(int|string $userId): array
    {
        return $this->transport->request('GET', '/ig/user/info/' . self::digits($userId, 'user_id'));
    }

    /** GET /ig/user/username_info/{username} - profile by username. */
    public function userByUsername(int|string $username): array
    {
        return $this->transport->request(
            'GET',
            '/ig/user/username_info/' . self::length($username, 'username', 1, 64)
        );
    }

    /** GET /profile/{user_id} - profile by numeric id. */
    public function profile(int|string $userId): array
    {
        return $this->transport->request('GET', '/profile/' . self::digits($userId, 'user_id'));
    }

    /**
     * GET /user/about/{user_id} - About this account.
     *
     * The "About this account" screen: country, registration month,
     * former usernames count, accounts-with-shared-followers count,
     * verification. Sparse accounts return null fields - check
     * `has_about_data` / `unavailable` before using them.
     */
    public function userAbout(int|string $userId): array
    {
        return $this->transport->request('GET', '/user/about/' . self::digits($userId, 'user_id'));
    }

    /**
     * GET /search/username/{query} - search users.
     *
     * `$count` is forwarded to the service, but the response may include
     * one extra exact-match entry beyond it.
     */
    public function searchUsername(int|string $query, ?int $count = 10): array
    {
        return $this->transport->request(
            'GET',
            '/search/username/' . self::quote($query),
            ['count' => self::count($count, 1, 50, 10)]
        );
    }

    /** GET /ig/user/similar/{user_id} - suggested accounts. */
    public function similarUsers(int|string $userId): array
    {
        return $this->transport->request('GET', '/ig/user/similar/' . self::digits($userId, 'user_id'));
    }

    // ---------- User content ----------

    /** GET /ig/user/media/{user_id} - profile grid posts. */
    public function userMedias(int|string $userId, ?int $count = 12, ?string $maxId = null): array
    {
        return $this->transport->request(
            'GET',
            '/ig/user/media/' . self::digits($userId, 'user_id'),
            self::page($count, $maxId, 12)
        );
    }

    /** GET /ig/user/tags/{user_id} - posts where the user is tagged. */
    public function userTags(int|string $userId, ?int $count = 12, ?string $maxId = null): array
    {
        return $this->transport->request(
            'GET',
            '/ig/user/tags/' . self::digits($userId, 'user_id'),
            self::page($count, $maxId, 12)
        );
    }

    /** GET /ig/user/stories/{user_id} - active stories. */
    public function userStories(int|string $userId): array
    {
        return $this->transport->request('GET', '/ig/user/stories/' . self::digits($userId, 'user_id'));
    }

    /** GET /ig/user/highlights/{user_id} - highlights tray. */
    public function userHighlights(int|string $userId): array
    {
        return $this->transport->request('GET', '/ig/user/highlights/' . self::digits($userId, 'user_id'));
    }

    /**
     * GET /ig/highlight/stories/{highlight_id} - media of a highlight.
     *
     * Accepts the tray id as is - the leading `highlight:` prefix
     * (as returned by `userHighlights()`) is trimmed automatically.
     */
    public function highlightStories(int|string $highlightId): array
    {
        $hid = \preg_replace('/^highlight:/', '', (string) $highlightId) ?? '';
        return $this->transport->request(
            'GET',
            '/ig/highlight/stories/' . self::quote(self::length($hid, 'highlight_id', 1, 64))
        );
    }

    // ---------- Followers / following ----------

    /**
     * GET /followers/{user_id} - followers list.
     *
     * On unverified accounts `count`, the `next_max_id` cursor and the
     * `query` filter work - nearly the full list is accessible and
     * searchable. On verified accounts (blue checkmark) Instagram exposes
     * no more than ~50 followers: one page, no cursor, `query` ignored.
     */
    public function followers(
        int|string $userId,
        int $count = 50,
        ?string $maxId = null,
        ?string $query = null,
    ): array {
        $params = self::page($count, $maxId, 50);
        if ($query !== null) {
            $params['query'] = $query;
        }

        return $this->transport->request('GET', '/followers/' . self::digits($userId, 'user_id'), $params);
    }

    /**
     * GET /following/{user_id} - following list.
     *
     * Page size: the service returns the default 25 accounts per page and
     * at most 50. The response carries `next_max_id` - pass it as `$maxId`
     * for the next page. `$query` filters the list by name.
     */
    public function following(
        int|string $userId,
        int $count = 25,
        ?string $maxId = null,
        ?string $query = null,
    ): array {
        $params = self::page($count, $maxId, 25, 50);
        if ($query !== null) {
            $params['query'] = $query;
        }

        return $this->transport->request('GET', '/following/' . self::digits($userId, 'user_id'), $params);
    }

    // ---------- Media ----------

    /**
     * GET /ig/media/info/{pk|shortcode} - full media card.
     *
     * Accepts a numeric pk or a post shortcode (e.g. "Deu3BoCBbDP"):
     * items[0] carries pk, code, media_type (1=photo, 2=video,
     * 8=carousel), caption, like/comment/view counts, taken_at, owner.
     */
    public function mediaInfo(int|string $media): array
    {
        return $this->transport->request('GET', '/ig/media/info/' . self::mediaArg($media));
    }

    /**
     * GET /ig/media/likers/{pk|shortcode} - users who liked the media.
     *
     * One shot of up to ~100 users; the route has no pagination.
     */
    public function mediaLikers(int|string $media): array
    {
        return $this->transport->request('GET', '/ig/media/likers/' . self::mediaArg($media));
    }

    /**
     * GET /ig/media/comments/{pk|shortcode} - media comments.
     *
     * Full pagination works only with sort_order="recent" (default):
     * pages are strictly chronological (newer -> older) and disjoint.
     * The cursor is `next_min_id` from the previous response - pass it
     * back as `$minId` verbatim. Collect until `next_min_id` is
     * null/empty or `comments` is an empty list.
     *
     * Instagram never returns `next_max_id` for this route - `max_id`
     * pagination must not be used. `$count` (1-50) is accepted by the
     * validator but the real page size is ~12-20 comments. Without
     * sort_order (ranked) the service returns a ~15 comment preview
     * whose pages overlap between pool accounts.
     */
    public function mediaComments(
        int|string $media,
        ?int $count = null,
        ?string $sortOrder = 'recent',
        ?string $minId = null,
    ): array {
        $params = [];
        if ($count !== null) {
            $params['count'] = self::count($count);
        }
        if ($sortOrder !== null) {
            if (!\in_array($sortOrder, ['recent', 'ranked'], true)) {
                throw new \InvalidArgumentException(
                    'sort_order must be "recent" or "ranked", got: ' . $sortOrder
                );
            }
            $params['sort_order'] = $sortOrder;
        }
        if ($minId !== null) {
            $params['min_id'] = $minId;
        }

        return $this->transport->request('GET', '/ig/media/comments/' . self::mediaArg($media), $params);
    }

    /**
     * GET /ig/media/search_clips/{keyword} - search reels by keyword.
     *
     * The service returns its native page (~12 clips); a page-size
     * parameter is not supported by the API for this route.
     */
    public function searchClips(int|string $keyword): array
    {
        return $this->transport->request(
            'GET',
            '/ig/media/search_clips/' . self::quote(self::length($keyword, 'keyword', 1, 100))
        );
    }

    // ---------- Hashtags ----------

    /** GET /ig/hashtag/info/{tag} - hashtag card. A leading # is trimmed. */
    public function hashtagInfo(int|string $tag): array
    {
        return $this->transport->request('GET', '/ig/hashtag/info/' . self::tag($tag));
    }

    /**
     * GET /ig/hashtag/search/{query} - search hashtags.
     *
     * Returns the full first page of tag matches; a page-size parameter
     * is not supported by the API for this route.
     */
    public function hashtagSearch(int|string $query): array
    {
        return $this->transport->request('GET', '/ig/hashtag/search/' . self::quote($query));
    }

    /**
     * GET /v2/hashtag/posts/{tag} - posts by hashtag. A leading # is trimmed.
     *
     * The service returns its native page (~15-18 posts) plus `next_max_id`
     * for pagination via `$maxId`; a page-size parameter is not supported.
     */
    public function hashtagPosts(int|string $tag, ?string $maxId = null): array
    {
        $params = [];
        if ($maxId !== null) {
            $params['max_id'] = $maxId;
        }

        return $this->transport->request('GET', '/v2/hashtag/posts/' . self::tag($tag), $params);
    }

    // ---------- Locations ----------

    /** GET /ig/location/info/{pk} - location card. */
    public function locationInfo(int|string $pk): array
    {
        return $this->transport->request('GET', '/ig/location/info/' . self::digits($pk, 'pk'));
    }

    /**
     * GET /ig/location/media/{pk} - media by location.
     *
     * The service returns its native page; pass `next_max_id` from a
     * previous response as `$maxId` to request the next page. The
     * response also carries `next_page` - a page counter, not a cursor.
     */
    public function locationMedia(int|string $pk, ?string $maxId = null): array
    {
        $params = [];
        if ($maxId !== null) {
            $params['max_id'] = $maxId;
        }

        return $this->transport->request('GET', '/ig/location/media/' . self::digits($pk, 'pk'), $params);
    }

    // ---------- Validators ----------

    private static function quote(int|string $value): string
    {
        return \rawurlencode((string) $value);
    }

    /** Numeric media pk or shortcode (e.g. DehVsgBi3sH). */
    private static function mediaArg(int|string $value): string
    {
        $s = (string) $value;
        if (\ctype_digit($s) && $s !== '') {
            return self::digits($s, 'media id');
        }
        if (\preg_match('/^[A-Za-z0-9_-]{5,64}$/', $s) === 1) {
            return $s;
        }
        throw new \InvalidArgumentException(
            "media id must be a numeric pk or a shortcode, got: {$s}"
        );
    }

    private static function digits(int|string $value, string $name, int $maxLen = 20): string
    {
        $s = (string) $value;
        if (!\ctype_digit($s) || \strlen($s) < 1 || \strlen($s) > $maxLen) {
            throw new \InvalidArgumentException("{$name} must be 1-{$maxLen} digits, got: {$s}");
        }

        return $s;
    }

    private static function length(int|string $value, string $name, int $lo, int $hi): string
    {
        $s = (string) $value;
        if (\strlen($s) < $lo || \strlen($s) > $hi) {
            throw new \InvalidArgumentException("{$name} must be {$lo}-{$hi} characters, got: {$s}");
        }

        return $s;
    }

    private static function tag(int|string $value): string
    {
        $s = \ltrim((string) $value, '#');

        return self::quote(self::length($s, 'tag', 1, 64));
    }

    private static function count(mixed $value, int $lo = 1, int $hi = 50, ?int $default = null): ?int
    {
        if ($value === null) {
            return $default;
        }
        if (!\is_int($value) || $value < $lo || $value > $hi) {
            throw new \InvalidArgumentException("count must be an integer {$lo}-{$hi}, got: {$value}");
        }

        return $value;
    }

    /**
     * @return array<string, mixed>
     */
    private static function page(?int $count, ?string $maxId, int $default, int $hi = 50): array
    {
        $params = ['count' => self::count($count, 1, $hi, $default)];
        if ($maxId !== null) {
            $params['max_id'] = $maxId;
        }

        return $params;
    }
}
