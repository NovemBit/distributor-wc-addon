<?php
/**
 * Require add-on files and perform their initial setup
 *
 * @package distributor-wc
 */

/* Require plug-in files */
require_once __DIR__ . '/includes/wc-hub.php';
require_once __DIR__ . '/includes/wc-spoke.php';
require_once __DIR__ . '/includes/rest-api.php';
require_once __DIR__ . '/includes/utils.php';

/* Call the setup functions */
\DT\NbAddon\WC\Hub\setup();

\DT\NbAddon\WC\Spoke\setup();
\DT\NbAddon\WC\RestApi\setup();
