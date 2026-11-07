<?php
/*
 * RB Disable Comments — promo banner
 *
 * Only the mount point. The banner itself (the PRO offer on top, while PRO is
 * not unlocked, and the RoboSoft services below it) is drawn by
 * assets/js/rb-admin-settings.js inside its own Shadow DOM, like the settings:
 * nothing another plugin's stylesheet or banner does can reach it, and its
 * styles reach nothing outside it.
 */

if ( ! defined( 'WPINC' ) ) {
	die();
}
?>
<div id="rb-disable-comments-banner" style="margin:16px 25px 16px 2px;max-width:980px;"></div>
