<?php

namespace Doctrine\DBAL\Migrations\Tests\Tools\Console\Command;

use org\bovigo\vfs\vfsStream;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Migrations\Provider\SchemaProviderInterface;
use Doctrine\DBAL\Migrations\Provider\StubSchemaProvider;
use Doctrine\DBAL\Migrations\Tools\Console\Command\DiffCommand;

class DiffCommandTest extends CommandTestCase
{
    const VERSION                       = '20160705000000';
    const CUSTOM_RELATIVE_TEMPLATE_NAME = 'tests/Doctrine/DBAL/Migrations/Tests/Tools/Console/Command/_files/migration.tpl';
    const CUSTOM_ABSOLUTE_TEMPLATE_NAME = __DIR__ . '/_files/migration.tpl';

    private $schema;
    private $root;
    private $migrationFile;

    public function testCommandCreatesNewMigrationsFileWithAVersionFromConfiguration() : void
    {
        $this->willGenerateVersionNumber();

        [$tester, $statusCode] = $this->executeCommand([]);

        self::assertSame(0, $statusCode);
        self::assertContains($this->migrationFile, $tester->getDisplay());
        self::assertTrue($this->root->hasChild($this->migrationFile));
        $content = $this->root->getChild($this->migrationFile)->getContent();
        self::assertContains('class Version' . self::VERSION, $content);
        self::assertContains('CREATE TABLE example', $content);
    }

    public function testCommandCreatesNewMigrationWithDownMethodContainingDropSql()
    {
        $this->willGenerateVersionNumber();

        [$tester, $statusCode] = $this->executeCommand([]);

        self::assertSame(0, $statusCode);
        self::assertTrue($this->root->hasChild($this->migrationFile));
        $content = $this->root->getChild($this->migrationFile)->getContent();
        self::assertContains('class Version' . self::VERSION, $content);
        self::assertContains('DROP TABLE example', $content);
    }

    /**
     * @group 504
     */
    public function testCommandIncludeDropTableStatementmentsInGeneratedUpMethodWithoutNoDrops()
    {
        $this->willGenerateVersionNumber();
        $this->connection->exec('CREATE TABLE other (id INT)');

        [$tester, $statusCode] = $this->executeCommand([]);

        self::assertSame(0, $statusCode);
        self::assertTrue($this->root->hasChild($this->migrationFile));
        $content = $this->root->getChild($this->migrationFile)->getContent();
        self::assertContains('DROP TABLE other', $content);
        self::assertContains('CREATE TABLE other', $content);
    }

    /**
     * @group 504
     */
    public function testCommandDoesNotIncludeDropTableStatementsIfNoDropsIsIncludedInTheArguments()
    {
        $this->willGenerateVersionNumber();
        $this->connection->exec('CREATE TABLE other (id INT)');

        [$tester, $statusCode] = $this->executeCommand(['--no-drops' => true]);

        self::assertSame(0, $statusCode);
        self::assertTrue($this->root->hasChild($this->migrationFile));
        $content = $this->root->getChild($this->migrationFile)->getContent();
        self::assertNotContains('DROP TABLE other', $content);
        self::assertNotContains('CREATE TABLE other', $content);
    }

    public static function provideCustomTemplateNames() : array
    {
        return [
            'relativePath' => [self::CUSTOM_RELATIVE_TEMPLATE_NAME],
            'absolutePath' => [self::CUSTOM_ABSOLUTE_TEMPLATE_NAME],
        ];
    }

    /**
     * @dataProvider provideCustomTemplateNames
     */
    public function testCommandCreatesNewMigrationsFileWithAVersionAndACustomTemplateFromConfiguration(string $templateName) : void
    {
        $this->willGenerateVersionNumber();

        $this->config->expects($this->once())
            ->method('getCustomTemplate')
            ->willReturn($templateName);

        [$tester, $statusCode] = $this->executeCommand([]);

        self::assertSame(0, $statusCode);
        self::assertContains($this->migrationFile, $tester->getDisplay());
        self::assertTrue($this->root->hasChild($this->migrationFile));
        $content = $this->root->getChild($this->migrationFile)->getContent();
        self::assertContains('class Version' . self::VERSION, $content);
        self::assertContains('CREATE TABLE example', $content);
        self::assertContains('public function customTemplate()', $content);
    }

    protected function setUp()
    {
        parent::setUp();

        $this->migrationFile = sprintf('Version%s.php', self::VERSION);
        $this->root          = vfsStream::setup('migrations');
        $this->config->method('getMigrationsDirectory')
            ->willReturn(vfsStream::url('migrations'));
    }

    protected function createCommand()
    {
        $this->schema = new Schema();
        $t      = $this->schema->createTable('example');
        $t->addColumn('id', 'integer', ['autoincrement' => true]);
        $t->setPrimaryKey(['id']);

        return new DiffCommand(new StubSchemaProvider($this->schema));
    }

    private function willGenerateVersionNumber()
    {
        $this->config->expects($this->once())
            ->method('generateVersionNumber')
            ->willReturn(self::VERSION);
    }
}
