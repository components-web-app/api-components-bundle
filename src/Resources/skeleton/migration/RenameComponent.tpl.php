<?php echo "<?php\n"; ?>

declare(strict_types=1);

namespace <?php echo $namespace; ?>;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class <?php echo $class_name; ?> extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rename component type <?php echo $old_name; ?> (dtype: <?php echo $old_dtype; ?>) to <?php echo $new_name; ?> (dtype: <?php echo $new_dtype; ?>)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("UPDATE abstract_component SET dtype = '<?php echo $new_dtype; ?>' WHERE dtype = '<?php echo $old_dtype; ?>'");
        $this->updateAllowedComponents('<?php echo $old_iri; ?>', '<?php echo $new_iri; ?>');
    }

    public function down(Schema $schema): void
    {
        $this->addSql("UPDATE abstract_component SET dtype = '<?php echo $old_dtype; ?>' WHERE dtype = '<?php echo $new_dtype; ?>'");
        $this->updateAllowedComponents('<?php echo $new_iri; ?>', '<?php echo $old_iri; ?>');
    }

    private function updateAllowedComponents(string $fromIri, string $toIri): void
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT id, allowed_components FROM component_group WHERE allowed_components IS NOT NULL AND allowed_components LIKE :pattern',
            ['pattern' => '%' . $fromIri . '%']
        );
        foreach ($rows as $row) {
            $components = json_decode((string) $row['allowed_components'], true);
            $updated = array_map(static fn (string $c): string => $c === $fromIri ? $toIri : $c, $components);
            $this->connection->executeStatement(
                'UPDATE component_group SET allowed_components = :components WHERE id = :id',
                ['components' => json_encode($updated), 'id' => $row['id']]
            );
        }
    }
}
