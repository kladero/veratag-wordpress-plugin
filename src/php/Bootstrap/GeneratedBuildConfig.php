<?php

namespace VeraTag\Bootstrap;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class GeneratedBuildConfig {
	public const BACKEND_BASE_URL = 'https://vera-tag.kladero.com/api';
	public const DASHBOARD_BASE_URL = 'https://vera-tag.kladero.com/app/';
	public const ALLOWED_BACKEND_BASE_URLS = array( 'https://vera-tag.kladero.com/api' );
	public const ALLOWED_DASHBOARD_BASE_URLS = array( 'https://vera-tag.kladero.com/app/' );
}
