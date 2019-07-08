<?php
/**
 * Require add-on files and perform their initial setup
 *
 * @package distributor-wc
 */

/* Require plug-in files */
require_once __DIR__ . '/includes/wc-hub.php';
require_once __DIR__ . '/includes/wc-spoke.php';

/* Call the setup functions */
\DT\NbAddon\WC\Hub\setup();

\DT\NbAddon\WC\Spoke\setup();
