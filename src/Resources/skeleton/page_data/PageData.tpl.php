<?php echo "<?php\n"; ?>

namespace <?php echo $namespace; ?>;

<?php echo $use_statements; ?>
#[ApiResource]
#[ORM\Entity]
class <?php echo $class_name; ?> extends AbstractPageData
{
<?php foreach ($properties as $prop) { ?>
    #[ORM\Column(nullable: <?php echo $prop['nullable'] ? 'true' : 'false'; ?>)]
    public <?php echo $prop['type']; ?> $<?php echo $prop['name']; ?>;

<?php } ?>
}
