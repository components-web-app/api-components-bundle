<?php echo "<?php\n"; ?>

namespace <?php echo $namespace; ?>;

<?php echo $use_statements; ?>
<?php if ($timestamped) { ?>#[Silverback\Timestamped]
<?php } ?>
<?php if ($publishable) { ?>#[Silverback\Publishable]
<?php } ?>
<?php if ($uploadable) { ?>#[Silverback\Uploadable]
<?php } ?>#[ApiResource]
#[ORM\Entity]
class <?php echo $class_name; ?> extends AbstractComponent
{
<?php if ($timestamped) { ?>    use TimestampedTrait;
<?php } ?>
<?php if ($publishable) { ?>    use PublishableTrait;
<?php } ?>
<?php if ($uploadable) { ?>    use UploadableTrait;

    #[Silverback\UploadableField(adapter: 'local')]
    public ?File $file = null;
<?php } ?>
}
