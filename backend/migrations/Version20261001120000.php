<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Entity\Item;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Hand-written on purpose: the partial indexes, the deterministic "C"
 * collation on normalized_name and text_pattern_ops are beyond what the
 * ORM schema tool can express.
 */
final class Version20261001120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create the item table with its indexes and seed the root folder.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE item (
                id UUID NOT NULL,
                parent_id UUID DEFAULT NULL,
                type VARCHAR(6) NOT NULL,
                name TEXT NOT NULL,
                normalized_name TEXT NOT NULL COLLATE "C",
                PRIMARY KEY (id)
            )
            SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE item
                ADD CONSTRAINT fk_item_parent_id_item_id
                FOREIGN KEY (parent_id) REFERENCES item (id) ON DELETE CASCADE
            SQL);
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX uniq_item_parent_normalized_name
                ON item (parent_id, normalized_name)
            SQL);
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX uniq_item_single_root
                ON item (parent_id) NULLS NOT DISTINCT
                WHERE parent_id IS NULL
            SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX idx_item_listing
                ON item (parent_id, type DESC, normalized_name, id)
            SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX idx_item_file_name_prefix
                ON item (normalized_name text_pattern_ops, id)
                WHERE type = 'file'
            SQL);
        $this->addSql(
            "INSERT INTO item (id, parent_id, type, name, normalized_name) VALUES (?, NULL, 'folder', 'Root', 'root')",
            [Item::ROOT_ID],
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE item');
    }
}
