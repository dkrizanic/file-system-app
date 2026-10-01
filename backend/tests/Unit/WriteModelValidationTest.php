<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\DTO\Write\CreateFile;
use App\DTO\Write\CreateFolder;
use App\DTO\Write\PaginationQuery;
use App\DTO\Write\RenameItem;
use App\DTO\Write\SearchQuery;
use App\DTO\Write\SearchScope;
use App\DTO\Write\SuggestionQuery;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class WriteModelValidationTest extends TestCase
{
    private ValidatorInterface $validator;

    protected function setUp(): void
    {
        $this->validator = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();
    }

    #[Test]
    #[TestDox('A blank folder name is rejected on name')]
    public function blank_folder_name_is_rejected(): void
    {
        $messages = $this->messagesOn(new CreateFolder(null, ''), 'name');

        self::assertSame(['Name must not be blank.'], $messages);
    }

    #[Test]
    #[TestDox('A whitespace-only folder name is rejected on name')]
    public function whitespace_only_folder_name_is_rejected(): void
    {
        self::assertNotEmpty($this->messagesOn(new CreateFolder(null, "  \t\n "), 'name'));
    }

    #[Test]
    #[TestDox('A folder name over 255 characters is rejected')]
    public function overlong_folder_name_is_rejected(): void
    {
        self::assertStringContainsString(
            '255',
            $this->firstMessage(new CreateFolder(null, str_repeat('a', 256)), 'name'),
        );
    }

    #[Test]
    #[TestDox('A folder name of exactly 255 characters is accepted')]
    public function boundary_length_folder_name_is_accepted(): void
    {
        self::assertSame([], $this->violationsOn(new CreateFolder(null, str_repeat('a', 255))));
    }

    #[Test]
    #[TestDox('Folder names containing a forward or back slash are rejected')]
    public function slashed_folder_names_are_rejected(): void
    {
        self::assertNotEmpty($this->messagesOn(new CreateFolder(null, 'a/b'), 'name'));
        self::assertNotEmpty($this->messagesOn(new CreateFolder(null, 'a\\b'), 'name'));
    }

    #[Test]
    #[TestDox('A folder name containing a control character is rejected')]
    public function control_character_folder_name_is_rejected(): void
    {
        self::assertNotEmpty($this->messagesOn(new CreateFolder(null, "a\u{0007}b"), 'name'));
    }

    #[Test]
    #[TestDox('The reserved names "." and ".." are rejected')]
    public function reserved_dot_names_are_rejected(): void
    {
        self::assertNotEmpty($this->messagesOn(new CreateFolder(null, '.'), 'name'));
        self::assertNotEmpty($this->messagesOn(new CreateFolder(null, '..'), 'name'));
    }

    #[Test]
    #[TestDox('A padded name is validated after trimming')]
    public function padded_name_is_validated_trimmed(): void
    {
        self::assertSame([], $this->violationsOn(new CreateFolder(null, ' Projects ')));
        self::assertNotEmpty($this->messagesOn(new CreateFolder(null, '   .   '), 'name'));
    }

    #[Test]
    #[TestDox('Dots inside a name are accepted')]
    public function dots_inside_name_are_accepted(): void
    {
        self::assertSame([], $this->violationsOn(new CreateFolder(null, 'notes.txt')));
    }

    #[Test]
    #[TestDox('A folder without parentId is accepted')]
    public function folder_without_parent_is_accepted(): void
    {
        self::assertSame([], $this->violationsOn(new CreateFolder(null, 'Docs')));
    }

    #[Test]
    #[TestDox('A file without parentId is rejected on parentId')]
    public function file_without_parent_is_rejected(): void
    {
        $messages = $this->messagesOn(new CreateFile(null, 'notes.txt'), 'parentId');

        self::assertSame(['The parent folder is required.'], $messages);
    }

    #[Test]
    #[TestDox('A file with parentId and name is accepted')]
    public function file_with_parent_and_name_is_accepted(): void
    {
        self::assertSame([], $this->violationsOn(new CreateFile($this->aUuid(), 'notes.txt')));
    }

    #[Test]
    #[TestDox('A blank rename is rejected on name')]
    public function blank_rename_is_rejected(): void
    {
        self::assertNotEmpty($this->messagesOn(new RenameItem('  '), 'name'));
    }

    #[Test]
    #[TestDox('A padded rename is accepted and validated trimmed')]
    public function padded_rename_is_accepted(): void
    {
        self::assertSame([], $this->violationsOn(new RenameItem(' Notes ')));
    }

    #[Test]
    #[TestDox('A pagination limit below 1 is rejected')]
    public function limit_below_range_is_rejected(): void
    {
        self::assertNotEmpty($this->messagesOn(new PaginationQuery(0, 0), 'limit'));
    }

    #[Test]
    #[TestDox('A pagination limit above 100 is rejected')]
    public function limit_above_cap_is_rejected(): void
    {
        self::assertNotEmpty($this->messagesOn(new PaginationQuery(101, 0), 'limit'));
    }

    #[Test]
    #[TestDox('A pagination limit at the bounds is accepted')]
    public function limit_at_bounds_is_accepted(): void
    {
        self::assertSame([], $this->violationsOn(new PaginationQuery(1, 0)));
        self::assertSame([], $this->violationsOn(new PaginationQuery(100, 0)));
    }

    #[Test]
    #[TestDox('A negative pagination offset is rejected')]
    public function negative_offset_is_rejected(): void
    {
        self::assertNotEmpty($this->messagesOn(new PaginationQuery(50, -1), 'offset'));
    }

    #[Test]
    #[TestDox('The pagination defaults are valid')]
    public function pagination_defaults_are_valid(): void
    {
        self::assertSame([], $this->violationsOn(new PaginationQuery()));
    }

    #[Test]
    #[TestDox('A blank search term is rejected on name')]
    public function blank_search_term_is_rejected(): void
    {
        self::assertNotEmpty($this->messagesOn(new SearchQuery('  '), 'name'));
    }

    #[Test]
    #[TestDox('A folder scope without folderId is rejected on folderId')]
    public function folder_scope_without_folder_id_is_rejected(): void
    {
        $messages = $this->messagesOn(new SearchQuery('notes', SearchScope::Folder, null), 'folderId');

        self::assertSame(['A folder scope requires a folderId.'], $messages);
    }

    #[Test]
    #[TestDox('A folder scope with folderId is accepted')]
    public function folder_scope_with_folder_id_is_accepted(): void
    {
        self::assertSame([], $this->violationsOn(new SearchQuery('notes', SearchScope::Folder, $this->aUuid())));
    }

    #[Test]
    #[TestDox('The all scope is accepted without a folderId')]
    public function all_scope_without_folder_id_is_accepted(): void
    {
        self::assertSame([], $this->violationsOn(new SearchQuery('notes')));
    }

    #[Test]
    #[TestDox('A blank suggestion prefix is accepted')]
    public function blank_suggestion_prefix_is_accepted(): void
    {
        self::assertSame([], $this->violationsOn(new SuggestionQuery('')));
    }

    /**
     * @return list<ConstraintViolationInterface>
     */
    private function violationsOn(object $model): array
    {
        return iterator_to_array($this->validator->validate($model), false);
    }

    /**
     * @return list<string>
     */
    private function messagesOn(object $model, string $field): array
    {
        $messages = [];
        foreach ($this->validator->validate($model) as $violation) {
            if ($violation->getPropertyPath() === $field) {
                $messages[] = strtr($violation->getMessage(), $violation->getParameters());
            }
        }

        return $messages;
    }

    private function firstMessage(object $model, string $field): string
    {
        return $this->messagesOn($model, $field)[0] ?? '';
    }

    private function aUuid(): Uuid
    {
        return Uuid::v7();
    }
}
