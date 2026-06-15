<?php
/**
 * Front controller for the GlotPress Blank theme.
 *
 * Renders nothing by design. The translate.chrdm.de subsite serves GlotPress
 * and the Traduttore REST API, neither of which uses the active theme. A
 * request that reaches this template is outside GlotPress, so it returns an
 * empty document instead of exposing an unstyled WordPress page.
 *
 * @package GlotPress_Blank
 */
