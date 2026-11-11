<?php

declare(strict_types=1);

use GetSocialAPI\Instagram\Client;
use GetSocialAPI\Instagram\Exception\APIKeyError;
use GetSocialAPI\Instagram\Exception\BadRequestError;
use GetSocialAPI\Instagram\Exception\GetSocialAPIError;
use GetSocialAPI\Instagram\Exception\InstagramError;
use GetSocialAPI\Instagram\Exception\NotFoundError;
use GetSocialAPI\Instagram\Exception\RateLimitError;
use GetSocialAPI\Instagram\Exception\ServiceUnavailableError;
use GetSocialAPI\Instagram\Exception\UnauthorizedError;
use GetSocialAPI\Instagram\InstagramAPI;
use GetSocialAPI\Instagram\Transport;
use GetSocialAPI\Instagram\TransportInterface;

// Minimal PSR-4 autoloader so tests run without composer.
\spl_autoload_register(static function (string $class): void {
    $prefix = 'GetSocialAPI\\Instagram\\';
    if (\str_starts_with($class, $prefix)) {
        $path = __DIR__ . '/../src/' . \str_replace('\\', '/', \substr($class, \strlen($prefix))) . '.php';
        if (\is_file($path)) {
            require $path;
        }
    }
});

$checks = 0;

function ok(mixed $cond, string $what): void
{
    global $checks;
    $checks++;
    if (!$cond) {
        \fwrite(STDERR, "FAIL: {$what}\n");
        exit(1);
    }
}

function throws(callable $fn, string $class, string $what): void
{
    try {
        $fn();
    } catch (\Throwable $e) {
        ok($e instanceof $class, "{$what} (got " . \get_class($e) . ')');
        return;
    }
    ok(false, "{$what} - no exception thrown");
}

final class FakeTransport implements TransportInterface
{
    public array $calls = [];

    public function __construct(private readonly array $response = [])
    {
    }

    public function request(string $method, string $path, array $params = [], bool $auth = true): array
    {
        $this->calls[] = [$method, $path, $params, $auth];

        return $this->response;
    }
}

// ---------- route mapping ----------

$t = new FakeTransport();
$ig = new InstagramAPI($t);

$ig->userInfo(25025320);
$ig->userByUsername('durov');
$ig->profile(25025320);
$ig->userAbout(25025320);
$ig->searchUsername('durov');
$ig->similarUsers(25025320);
$ig->userMedias(25025320);
$ig->userMedias(25025320, 5, 'CURSOR');
$ig->userTags(25025320);
$ig->userStories(25025320);
$ig->userHighlights(25025320);
$ig->highlightStories('highlight:17895462');
$ig->followers(43586162288, 25);
$ig->following(25025320);
$ig->following(25025320, 30, 'CUR', 'durov');
$ig->mediaInfo('Deu3BoCBbDP');
$ig->mediaLikers(4012386322294878415);
$ig->mediaComments(4012386322294878415, 50, 'recent');
$ig->searchClips('cats');
$ig->hashtagInfo('#photography');
$ig->hashtagSearch('photo');
$ig->hashtagPosts('photography');
$ig->locationInfo(213385402);
$ig->locationMedia(213385402);

$expected = [
    ['GET', '/ig/user/info/25025320', [], true],
    ['GET', '/ig/user/username_info/durov', [], true],
    ['GET', '/profile/25025320', [], true],
    ['GET', '/user/about/25025320', [], true],
    ['GET', '/search/username/durov', ['count' => 10], true],
    ['GET', '/ig/user/similar/25025320', [], true],
    ['GET', '/ig/user/media/25025320', ['count' => 12], true],
    ['GET', '/ig/user/media/25025320', ['count' => 5, 'max_id' => 'CURSOR'], true],
    ['GET', '/ig/user/tags/25025320', ['count' => 12], true],
    ['GET', '/ig/user/stories/25025320', [], true],
    ['GET', '/ig/user/highlights/25025320', [], true],
    ['GET', '/ig/highlight/stories/17895462', [], true],
    ['GET', '/followers/43586162288', ['count' => 25], true],
    ['GET', '/following/25025320', ['count' => 25], true],
    ['GET', '/following/25025320', ['count' => 30, 'max_id' => 'CUR', 'query' => 'durov'], true],
    ['GET', '/ig/media/info/Deu3BoCBbDP', [], true],
    ['GET', '/ig/media/likers/4012386322294878415', [], true],
    ['GET', '/ig/media/comments/4012386322294878415', ['count' => 50, 'sort_order' => 'recent'], true],
    ['GET', '/ig/media/search_clips/cats', [], true],
    ['GET', '/ig/hashtag/info/photography', [], true],
    ['GET', '/ig/hashtag/search/photo', [], true],
    ['GET', '/v2/hashtag/posts/photography', [], true],
    ['GET', '/ig/location/info/213385402', [], true],
    ['GET', '/ig/location/media/213385402', [], true],
];

ok(\count($t->calls) === \count($expected), 'route count');
foreach ($expected as $i => $exp) {
    ok($t->calls[$i] === $exp, "route #{$i}: " . \json_encode($t->calls[$i]));
}

// ---------- unicode + cursor encoding ----------

$ig->hashtagInfo('#природа');
ok(\end($t->calls)[1] === '/ig/hashtag/info/' . \rawurlencode('природа'), 'tag urlencoded');
$ig->mediaComments('Deu3BoCBbDP', null, null, '{"min_id":"x"}');
$last = \end($t->calls);
ok($last[2]['min_id'] === '{"min_id":"x"}', 'min_id passed verbatim');
ok(!isset($last[2]['count']), 'null count omitted');
ok(!isset($last[2]['sort_order']) || $last[2]['sort_order'] === 'recent', 'null sort_order omitted');

// ---------- validation ----------

throws(fn () => $ig->userInfo(-1), InvalidArgumentException::class, 'negative user id');
throws(fn () => $ig->userInfo('123456789012345678901'), InvalidArgumentException::class, 'user id too long');
throws(fn () => $ig->userByUsername(''), InvalidArgumentException::class, 'empty username');
throws(fn () => $ig->mediaInfo('!!'), InvalidArgumentException::class, 'bad media arg');
throws(fn () => $ig->mediaComments('Deu3BoCBbDP', null, 'hot'), InvalidArgumentException::class, 'bad sort_order');
throws(fn () => $ig->userMedias(25025320, 0), InvalidArgumentException::class, 'count too low');
throws(fn () => $ig->following(25025320, 51), InvalidArgumentException::class, 'following count too high');
throws(fn () => $ig->searchClips(''), InvalidArgumentException::class, 'empty keyword');

// ---------- client ----------

throws(fn () => new Client(), InvalidArgumentException::class, 'client without key');
$client = new Client(apiKey: 'gsa_live_test');
ok($client->instagram instanceof InstagramAPI, 'client instagram property');

// ---------- transport response mapping ----------

$body = ['user' => ['pk' => 1]];
ok(Transport::parse(200, [], (string) \json_encode($body)) === $body, '200 returns body');

throws(fn () => Transport::parse(401, [], '{}'), UnauthorizedError::class, '401');
throws(fn () => Transport::parse(400, [], '{"error":"count must be 1-50","field":"count"}'), BadRequestError::class, '400');
throws(fn () => Transport::parse(404, [], '{"message":"user not found"}'), NotFoundError::class, '404');
throws(fn () => Transport::parse(503, [], '{}'), ServiceUnavailableError::class, '503');
throws(fn () => Transport::parse(420, [], '{"status":"fail","message":"checkpoint required"}'), InstagramError::class, 'fail status');
throws(fn () => Transport::parse(451, [], '{"error":"restricted"}'), GetSocialAPIError::class, 'other 4xx');

try {
    Transport::parse(400, [], '{"error":"count must be 1-50","field":"count"}');
} catch (BadRequestError $e) {
    ok($e->field === 'count', 'bad request field');
    ok(\str_contains($e->getMessage(), 'count must be 1-50'), 'bad request message');
}

try {
    Transport::parse(429, ['retry-after' => '7'], '{}');
} catch (RateLimitError $e) {
    ok($e->retryAfter === 7, 'retry-after parsed');
}

try {
    Transport::parse(401, [], '{"error":{"message":"bad key"}}');
} catch (UnauthorizedError $e) {
    ok($e->getMessage() === 'Invalid API key', '401 message');
}

ok(new APIKeyError() instanceof UnauthorizedError, 'APIKeyError alias');

\printf("OK (%d checks)\n", $checks);
