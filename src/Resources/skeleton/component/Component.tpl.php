<?= "<?php\n" ?>

namespace <?= $namespace; ?>;

<?= $use_statements; ?>
<?php if ($timestamped): ?>#[Silverback\Timestamped]
<?php endif; ?>
<?php if ($publishable): ?>#[Silverback\Publishable]
<?php endif; ?>
<?php if ($uploadable): ?>#[Silverback\Uploadable]
<?php endif; ?>#[ApiResource]
#[ORM\Entity]
class <?= $class_name ?> extends AbstractComponent
{
<?php if ($timestamped): ?>    use TimestampedTrait;
<?php endif; ?>
<?php if ($publishable): ?>    use PublishableTrait;
<?php endif; ?>
<?php if ($uploadable): ?>    use UploadableTrait;

    #[Silverback\UploadableField(adapter: 'local')]
    public ?File $file = null;
<?php endif; ?>
}
