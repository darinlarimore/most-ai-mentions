<?php

namespace App\Services;

class DomainFilterService
{
    /** @var list<string> */
    private const BLOCKED_TLDS = [
        '.xxx', '.porn', '.sex', '.adult', '.sexy', '.cam', '.sucks',
    ];

    /** @var list<string> */
    private const BLOCKED_KEYWORDS = [
        'porn', 'xxx', 'xvideo', 'xhamster', 'redtube', 'youporn',
        'brazzers', 'xnxx', 'hentai', 'onlyfans', 'chaturbate',
        'livejasmin', 'stripchat', 'bongacams', 'cam4', 'fapello',
        'spankbang', 'nhentai', 'rule34', 'e621', 'gelbooru',
        'danbooru', 'fakku', 'hanime',
    ];

    /** @var list<string> */
    private const BLOCKED_DOMAINS = [
        'pornhub.com',
        'xvideos.com',
        'xnxx.com',
        'xhamster.com',
        'redtube.com',
        'youporn.com',
        'brazzers.com',
        'onlyfans.com',
        'chaturbate.com',
        'livejasmin.com',
        'stripchat.com',
        'bongacams.com',
        'cam4.com',
        'spankbang.com',
        'tube8.com',
        'beeg.com',
        'fapello.com',
        'nhentai.net',
        'rule34.xxx',
        'e621.net',
        'gelbooru.com',
        'danbooru.donmai.us',
        'fakku.net',
        'hanime.tv',
        'hentaihaven.xxx',
        'motherless.com',
        'efukt.com',
        'heavy-r.com',
        'omegle.com',
        'myfreecams.com',
        'camsoda.com',
        'flirt4free.com',
    ];

    public function isBlocked(string $domain): bool
    {
        $domain = strtolower(preg_replace('/^www\./', '', $domain));

        if ($this->hasBlockedTld($domain)) {
            return true;
        }

        if ($this->isBlockedDomain($domain)) {
            return true;
        }

        if ($this->hasBlockedKeyword($domain)) {
            return true;
        }

        return false;
    }

    private function hasBlockedTld(string $domain): bool
    {
        foreach (self::BLOCKED_TLDS as $tld) {
            if (str_ends_with($domain, $tld)) {
                return true;
            }
        }

        return false;
    }

    private function isBlockedDomain(string $domain): bool
    {
        foreach (self::BLOCKED_DOMAINS as $blocked) {
            if ($domain === $blocked || str_ends_with($domain, '.'.$blocked)) {
                return true;
            }
        }

        return false;
    }

    private function hasBlockedKeyword(string $domain): bool
    {
        // Check the domain name part only (strip TLD to avoid false positives)
        $domainWithoutTld = implode('.', array_slice(explode('.', $domain), 0, -1));

        foreach (self::BLOCKED_KEYWORDS as $keyword) {
            if (str_contains($domainWithoutTld, $keyword)) {
                return true;
            }
        }

        return false;
    }
}
