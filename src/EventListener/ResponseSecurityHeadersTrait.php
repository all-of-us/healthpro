<?php

namespace App\EventListener;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Response;

trait ResponseSecurityHeadersTrait
{
    private static string $frameAncestors = '*.my.site.com *.my.salesforce.com *.vf.force.com *.lightning.force.com *.joinallofus.org';

    public function addSecurityHeaders(Response $response, ParameterBagInterface $params)
    {
        $frameAncestors = $params->has('frame_ancestors') ? $params->get('frame_ancestors') : self::$frameAncestors;
        // prevent clickjacking attacks
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');

        // define content security policy
        $contentSecurityPolicy = 'default-src'
            . " 'self'" // allow all local content
            . " 'unsafe-eval'" // required for setTimeout and setInterval
            . " 'unsafe-inline'" // for the places we are using inline JS
            . ' www.google-analytics.com www.googletagmanager.com' // Google Analytics
            . ' storage.googleapis.com' // for SOP PDFs stored in a Google Storage bucket
            . ' fonts.googleapis.com' // for custom display and body fonts
            . ' fonts.gstatic.com' // for custom display and body fonts
            . ' www.youtube.com' // for training videos hosted on YouTube
            . ' *.kaltura.com' // for training videos hosted on Kaltura
            . ' cdn.plot.ly;' // allow plot.ly remote requests

            . " img-src www.google-analytics.com 'self' data:;" // allow Google Analytcs, self, and data: urls for img src

            . " frame-ancestors 'self' $frameAncestors"; // accomplishes the same as the X-Frame-Options header above

        $response->headers->set('Content-Security-Policy', $contentSecurityPolicy);

        // prevent browsers from sending unencrypted requests
        $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains; preload');

        // "low" security finding: prevent MIME type sniffing
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        // "low" security finding: enable XSS Protection
        // http://blog.innerht.ml/the-misunderstood-x-xss-protection/
        $response->headers->set('X-XSS-Protection', '1; mode=block');

        // Default cache control header in ResponseHeaderBag::computeCacheControlValue returns "no-cache, private"
        // Recommendation from security team is to add no-store as well.
        $response->headers->addCacheControlDirective('no-cache, no-store');
    }
}
