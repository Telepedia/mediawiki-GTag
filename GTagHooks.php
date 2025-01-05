use MediaWiki\MediaWikiServices;

class GTagHooks {
    /**
     * Add tracking js to page
     *
     * @param OutputPage $out
     * @param Skin $sk
     */
    public static function onBeforePageDisplay( OutputPage $out, Skin $sk ) {
        $user = $out->getUser();
        $config = $out->getConfig();
        $request = $out->getRequest();
        $permMan = MediaWikiServices::getInstance()->getPermissionManager();
        $skinName = $sk->getSkinName();
        $gaId = $config->get( 'GTagAnalyticsId' );
        $anonymizeIP = $config->get( 'GTagAnonymizeIP' );
        $honorDNT = $config->get( 'GTagHonorDNT' );
        $enableTCF = $config->get( 'GTagEnableTCF' );
        $trackSensitive = $config->get( 'GTagTrackSensitivePages' );

        if ( $gaId === '' || !preg_match( '/^(UA-[0-9]+-[0-9]+|G-[0-9A-Z]+)$/', $gaId ) ) {
            return; // Invalid or missing GA ID
        }

        if ( !$trackSensitive ) {
            $allowed = $out->getAllowedModules( ResourceLoaderModule::TYPE_SCRIPTS );
            if ( $allowed < ResourceLoaderModule::ORIGIN_USER_SITEWIDE ) {
                return; // Don't track sensitive pages
            }
        }

        if ( $honorDNT ) {
            $out->addVaryHeader( 'DNT' );
            if ( $request->getHeader( 'DNT' ) === '1' ) {
                return; // Respect DNT header
            }
        }

        if ( $permMan->userHasRight( $user, 'gtag-exempt' ) ) {
            return; // User is exempt from tracking
        }

        $gtConfig = [];
        if ( $anonymizeIP ) {
            $gtConfig['anonymize_ip'] = true;
        }
        $gtConfigJson = json_encode( $gtConfig ) ?: '{}';

        $tcfLine = $enableTCF ? 'window["gtag_enable_tcf_support"] = true;' : '';

        // Add the <script> tag for gtag.js to the head
        $out->addHeadItem( 'GTagScript', Html::element( 'script', [
            'src' => "https://www.googletagmanager.com/gtag/js?id=$gaId",
            'async' => true,
            'nonce' => $out->getCSP()->getNonce()
        ] ) );

        // Add inline JS for gtag initialization to the head
        $out->addHeadItem( 'GTagInit', Html::rawElement( 'script', [],
            <<<EOS
window.dataLayer = window.dataLayer || [];
$tcfLine
function gtag(){dataLayer.push(arguments);}
gtag('js', new Date());
gtag('config', '$gaId', $gtConfigJson);
gtag('event', 'mediawiki_statistics', {
    'skin': '$skinName'
});
EOS
        ) );
    }
}
