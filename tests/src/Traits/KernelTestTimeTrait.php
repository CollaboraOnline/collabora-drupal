<?php

/*
 * Copyright the Collabora Online contributors.
 *
 * SPDX-License-Identifier: MPL-2.0
 *
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/.
 */

declare(strict_types=1);

namespace Drupal\Tests\collabora_online\Traits;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\DependencyInjection\ContainerBuilder;

/**
 * Registers a mock time service.
 */
trait KernelTestTimeTrait {

  /**
   * Value to return for getRequestTime() from the mock time service.
   *
   * @var \DateTimeImmutable
   */
  protected \DateTimeImmutable $mockRequestTime;

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container) {
    parent::register($container);
    $time_mock = $this->createMock(TimeInterface::class);
    $time_mock->method('getRequestTime')
      ->willReturnCallback(fn (): int => $this->mockRequestTime->getTimestamp());
    $container->set('datetime.time', $time_mock);

    // By default, set the regular request time.
    $this->mockRequestTime = \DateTimeImmutable::createFromFormat(
      'U',
      (string) $_SERVER['REQUEST_TIME'],
    );
  }

}
