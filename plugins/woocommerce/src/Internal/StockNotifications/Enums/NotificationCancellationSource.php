<?php

declare(strict_types=1);

namespace Automattic\WooCommerce\Internal\StockNotifications\Enums;

/**
 * Notification cancellation source enum.
 */
final class NotificationCancellationSource {

	/**
	 * Admin cancellation source.
	 *
	 * @var string
	 */
	public const ADMIN = 'admin';

	/**
	 * User cancellation source.
	 *
	 * @var string
	 */
	public const USER = 'user';

	/**
	 * System cancellation source.
	 *
	 * @var string
	 */
	public const SYSTEM = 'system';

	/**
	 * Cancellation initiated by the customer from their My Account page.
	 *
	 * Distinguished from USER (which represents an unsubscribe via the
	 * email-link handler) so reports can show where each cancellation
	 * originated.
	 *
	 * @var string
	 */
	public const MY_ACCOUNT = 'my-account';

	/**
	 * Get valid cancellation sources.
	 *
	 * @return string[]
	 */
	public static function get_valid_cancellation_sources(): array {
		return array(
			self::ADMIN,
			self::USER,
			self::SYSTEM,
			self::MY_ACCOUNT,
		);
	}
}
