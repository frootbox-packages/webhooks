<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Webhooks\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Frootbox\Database\Migration;
use Frootbox\Database\Schema\Blueprint;

/**
 * Webhooks and their deliveries (decision 0253).
 */
final class Version20261217090000 extends Migration
{
    public function getDescription(): string
    {
        return 'Adds webhooks and their deliveries';
    }

    public function up(Schema $schema): void
    {
        $this->createTable('frootbox_webhooks_webhooks', function (Blueprint $table): void {
            $table->ulid();
            $table->string('title', 100);
            $table->string('url', 2000);
            $table->json('actions');
            $table->text('secret');
            $table->dateTime('active_since');
            $table->dateTime('paused_at')->nullable();
            $table->string('paused_reason', 20)->nullable();
            $table->timestamps();
        });

        $this->createTable('frootbox_webhooks_deliveries', function (Blueprint $table): void {
            $table->ulid();
            $table->string('webhook_id', 26);
            $table->string('action', 255);
            $table->integer('entry_id')->nullable();
            $table->text('body');
            $table->string('status', 20);
            $table->integer('attempts')->default(0);
            $table->integer('response_code')->nullable();
            $table->integer('duration_ms')->nullable();
            $table->string('error', 2000)->nullable();
            $table->dateTime('first_failed_at')->nullable();
            $table->dateTime('attempted_at')->nullable();
            $table->dateTime('delivered_at')->nullable();
            $table->dateTime('created_at');
            $table->index(['webhook_id', 'created_at']);
            $table->index('created_at');
        });
    }

    public function down(Schema $schema): void
    {
        $this->dropTable('frootbox_webhooks_deliveries');
        $this->dropTable('frootbox_webhooks_webhooks');
    }
}
