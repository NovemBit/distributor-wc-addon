<?php
/**
 * Require add-on files and perform their initial setup
 *
 * @package distributor-{ Add-on slug }
 */

/* Require plug-in files */
require_once __DIR__ . '/includes/{ Add-on slug }-hub.php';
require_once __DIR__ . '/includes/{ Add-on slug }-spoke.php';

/* Call the setup functions */
\DT\NbAddon\{ Add - on namespace }\Hub\setup();

\DT\NbAddon\{ Add - on namespace }\Spoke\setup();
