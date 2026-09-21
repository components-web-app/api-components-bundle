$this->addSql('UPDATE <?php echo $component_table; ?> SET dtype = :to WHERE dtype = :from', ['to' => <?php echo var_export($to_dtype, true); ?>, 'from' => <?php echo var_export($from_dtype, true); ?>]);
foreach ($this->connection->fetchAllAssociative('SELECT id, allowed_components FROM <?php echo $group_table; ?> WHERE allowed_components IS NOT NULL') as $row) {
    $components = json_decode((string) $row['allowed_components'], true);
    if (!\is_array($components) || !\in_array(<?php echo var_export($from_iri, true); ?>, $components, true)) {
        continue;
    }
    $this->connection->executeStatement(
        'UPDATE <?php echo $group_table; ?> SET allowed_components = :components WHERE id = :id',
        ['components' => json_encode(array_map(static fn (string $c): string => <?php echo var_export($from_iri, true); ?> === $c ? <?php echo var_export($to_iri, true); ?> : $c, $components)), 'id' => $row['id']]
    );
}
