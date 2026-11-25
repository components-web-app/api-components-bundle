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
foreach ($this->connection->fetchAllAssociative('SELECT id, allowed_component_ui FROM <?php echo $group_table; ?> WHERE allowed_component_ui IS NOT NULL') as $row) {
    $rules = json_decode((string) $row['allowed_component_ui'], true);
    if (!\is_array($rules) || !\array_key_exists(<?php echo var_export($from_iri, true); ?>, $rules)) {
        continue;
    }
    $renamed = [];
    foreach ($rules as $iri => $rule) {
        $renamed[<?php echo var_export($from_iri, true); ?> === $iri ? <?php echo var_export($to_iri, true); ?> : $iri] = $rule;
    }
    $this->connection->executeStatement(
        'UPDATE <?php echo $group_table; ?> SET allowed_component_ui = :rules WHERE id = :id',
        ['rules' => json_encode($renamed), 'id' => $row['id']]
    );
}
