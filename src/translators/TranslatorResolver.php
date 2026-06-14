<?php

namespace vaersaagod\transmate\translators;

use yii\base\InvalidConfigException;

/**
 * Resolves which translator handle to use for a given translation,
 * based on the plugin's file-based `translator` config setting.
 *
 * This is a plain value object: a pure function of the config, with no
 * Craft or Yii coupling. Build one from the `translator` setting and call
 * resolve() per source → target pair. Normalization is memoized.
 *
 * Accepted config shapes:
 *
 *   // 1. Single translator for everything
 *   'translator' => 'deepl',
 *
 *   // 2. Per-site map with required wildcard fallback
 *   'translator' => [
 *       '*'       => 'deepl',
 *       'somali'  => 'openai',
 *   ],
 *
 *   // 3. Same as above, plus bidirectional pair overrides
 *   'translator' => [
 *       '*'                 => 'deepl',
 *       'somali'            => 'openai',
 *       'somali:icelandic'  => 'openai', // matches both directions
 *   ],
 */
class TranslatorResolver
{
    public const WILDCARD = '*';

    /**
     * Normalized config, memoized on first use:
     *   ['sites' => [handle => translator], 'pairs' => [sortedPairKey => translator]]
     *
     * @var array{sites: array<string, string>, pairs: array<string, string>}|null
     */
    private ?array $normalized = null;

    /**
     * @param string|array<string, string> $config The raw `translator` setting.
     */
    public function __construct(
        private readonly string|array $config,
    )
    {
    }

    /**
     * Resolves the translator handle for a source → target translation.
     *
     * @throws InvalidConfigException if the pair resolves ambiguously,
     *                                or no translator can be determined.
     */
    public function resolve(string $sourceSite, string $targetSite): string
    {
        $config = $this->normalize();

        // 1. A pair override always wins (bidirectional).
        $pairKey = $this->pairKey($sourceSite, $targetSite);
        if (isset($config['pairs'][$pairKey])) {
            return $config['pairs'][$pairKey];
        }

        // 2. Resolve each side independently.
        $source = $this->resolveSite($sourceSite, $config['sites']);
        $target = $this->resolveSite($targetSite, $config['sites']);

        // 3. Same translator on both sides → done.
        if ($source['handle'] === $target['handle']) {
            return $source['handle'];
        }

        // 4. Exactly one side is an explicit (non-wildcard) entry → it wins.
        if ($source['explicit'] !== $target['explicit']) {
            return $source['explicit'] ? $source['handle'] : $target['handle'];
        }

        // 5. Both explicit and disagreeing → genuinely ambiguous.
        throw new InvalidConfigException(sprintf(
            'Conflicting translators configured for "%s" (%s) and "%s" (%s). ' .
            'Add a pair override, e.g. \'%s\' => \'…\', to disambiguate.',
            $sourceSite, $source['handle'],
            $targetSite, $target['handle'],
            $pairKey,
        ));
    }

    /**
     * Resolves a single site to its translator, plus whether the match
     * was explicit (a site-specific key) or via the wildcard fallback.
     *
     * @param array<string, string> $sites
     * @return array{handle: string, explicit: bool}
     * @throws InvalidConfigException
     */
    private function resolveSite(string $site, array $sites): array
    {
        if (isset($sites[$site])) {
            return ['handle' => $sites[$site], 'explicit' => true];
        }

        if (isset($sites[self::WILDCARD])) {
            return ['handle' => $sites[self::WILDCARD], 'explicit' => false];
        }

        throw new InvalidConfigException(sprintf(
            'No translator configured for site "%s", and no "%s" fallback is set.',
            $site,
            self::WILDCARD,
        ));
    }

    /**
     * Normalizes the raw config into separate site and pair maps.
     *
     * @return array{sites: array<string, string>, pairs: array<string, string>}
     * @throws InvalidConfigException
     */
    private function normalize(): array
    {
        if ($this->normalized !== null) {
            return $this->normalized;
        }

        // Scalar form → wildcard-only map.
        if (is_string($this->config)) {
            $handle = trim($this->config);
            if ($handle === '') {
                throw new InvalidConfigException('The "translator" setting cannot be an empty string.');
            }

            return $this->normalized = [
                'sites' => [self::WILDCARD => $handle],
                'pairs' => [],
            ];
        }

        $sites = [];
        $pairs = [];

        foreach ($this->config as $key => $translator) {
            if (!is_string($key) || !is_string($translator) || trim($translator) === '') {
                throw new InvalidConfigException(sprintf(
                    'Invalid "translator" entry for key "%s": expected a non-empty string translator handle.',
                    is_string($key) ? $key : (string)$key,
                ));
            }

            $translator = trim($translator);

            if (str_contains($key, ':')) {
                [$a, $b] = $this->parsePairKey($key);
                $pairs[$this->pairKey($a, $b)] = $translator;
            } else {
                $sites[$key] = $translator;
            }
        }

        if (!isset($sites[self::WILDCARD])) {
            throw new InvalidConfigException(sprintf(
                'The "translator" map must include a "%s" fallback key.',
                self::WILDCARD,
            ));
        }

        return $this->normalized = ['sites' => $sites, 'pairs' => $pairs];
    }

    /**
     * Splits and validates a "source:target" pair key.
     *
     * @return array{0: string, 1: string}
     * @throws InvalidConfigException
     */
    private function parsePairKey(string $key): array
    {
        $parts = explode(':', $key);
        if (count($parts) !== 2 || trim($parts[0]) === '' || trim($parts[1]) === '') {
            throw new InvalidConfigException(sprintf(
                'Invalid translation pair key "%s": expected the format "sourceSite:targetSite".',
                $key,
            ));
        }

        return [trim($parts[0]), trim($parts[1])];
    }

    /**
     * Builds a direction-independent key for a site pair, so that
     * "somali:icelandic" and "icelandic:somali" collapse to the same entry.
     */
    private function pairKey(string $a, string $b): string
    {
        $sites = [$a, $b];
        sort($sites, SORT_STRING);

        return implode(':', $sites);
    }
}
