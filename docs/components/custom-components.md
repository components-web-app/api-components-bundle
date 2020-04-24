---
layout: default
parent: Components
nav_order: 3
---
# Custom components

> __Incomplete Documentation__

## Create a custom component

- Extending `AbstractComponent`

## Reusable traits

### ComponentGroupsTrait

(docs coming soon)

## Annotations

### Timestamped

- Implementing `TimestampedInterface` and `TimestampedTrait` (should probably refactor to work similar to `Publishable`)

### File

- Implementing `FileInterface` and `FileTrait` (should probably refactor to work similar to `Publishable`)

### Publishable

To set an entity component as publishable, use the following annotation and interface:

```php
use Silverback\ApiComponentBundle\Annotation as Silverback;
use Silverback\ApiComponentBundle\Entity\Utility\PublishableInterface;
use Silverback\ApiComponentBundle\Entity\Utility\PublishableTrait;

/**
 * @Silverback\Publishable
 */
class Foo implements PublishableInterface
{
    use PublishableTrait;
```

Default property is named `publishedAt` and association to original resource `publishedResource`. To customize those
properties, update the annotation and your class:

```php
use Silverback\ApiComponentBundle\Annotation as Silverback;
use Silverback\ApiComponentBundle\Entity\Utility\PublishableInterface;

/**
 * @Silverback\Publishable(fieldName="publicationDate", associationName="originalResource")
 */
class Foo implements PublishableInterface
{
    // If not set, the Doctrine mapping is automatically configured with type="date" nullable
    private $publicationDate;

    // If not set, the Doctrine mapping is automatically configured with OneToOne self-referenced association nullable
    private $originalResource;

    public function getPublicationDate(): ?\DateTimeInterface
    {
        return $this->publicationDate;
    }

    public function setPublicationDate(?\DateTimeInterface $publicationDate): self
    {
        $this->publicationDate = $publicationDate;

        return $this;
    }

    public function isPublished() : bool
    {
        return null !== $this->publicationDate && new \DateTimeImmutable() >= $this->publicationDate;
    }

    public function getOriginalResource(): ?self
    {
        return $this->publicationDate;
    }

    public function setOriginalResource(?self $originalResource): self
    {
        $this->originalResource = $originalResource;

        return $this;
    }
```

Configure the security with expression language, for users who have access to publishable resources:

```yaml
# config/packages/silverback_api_component.yaml:
silverback_api_component:
    # ...
    publishable:
        permission: "is_granted('ROLE_ADMIN')" # default value
```

By default, default validators are applied on save, even for draft objects. If you want to apply validators only for
published resource, configure it as following:

```php
use Silverback\ApiComponentBundle\Annotation as Silverback;
use Silverback\ApiComponentBundle\Entity\Utility\PublishableInterface;

/**
 * @Silverback\Publishable
 */
class Foo implements PublishableInterface
{
    /**
     * This constraint will be applied on draft and published resources.
     *
     * @Assert\NotBlank
     */
    public string $name = '';

    /**
     * This constraint will be applied on published resources only.
     *
     * @Assert\NotBlank(groups={"Foo:published"})
     */
    public string $description = '';
}
```

You can define a custom validation group for published resources:

```php
use Silverback\ApiComponentBundle\Annotation as Silverback;
use Silverback\ApiComponentBundle\Entity\Utility\PublishableInterface;

/**
 * @Silverback\Publishable(validationGroups={"custom_validation_group"})
 */
class Foo implements PublishableInterface
{
    /**
     * This constraint will be applied on draft and published resources.
     *
     * @Assert\NotBlank
     */
    public string $name = '';

    /**
     * This constraint will be applied on published resources only.
     * The "Foo:published" validation does not exist anymore.
     *
     * @Assert\NotBlank(groups={"custom_validation_group"})
     */
    public string $description = '';
}
```
