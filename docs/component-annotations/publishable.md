---
layout: default
title: Publishable
parent: Component Annotations
nav_order: 1
---
# Publishable

The easiest way to configure an entity resource as publishable, use the following annotation and trait:

```php
use Silverback\ApiComponentsBundle\Annotation as Silverback;
use Silverback\ApiComponentsBundle\Entity\Utility\PublishableTrait;

#[Silverback\Publishable]
class Foo
{
    use PublishableTrait;
```

The default property name are:
- `publishedAt` for the published time
- `publishedResource` is the association to published resource.
- `draftResource` is the reverse association to the draft resource

To customize these properties, you can update the annotation and your class:

```php
use Silverback\ApiComponentsBundle\Annotation as Silverback;

#[Silverback\Publishable(fieldName: 'publicationDate', associationName: 'originalResource', reverseAssociationName: 'newResource')]
class Foo
{
    // If not set, the Doctrine mapping is automatically configured with type="date" nullable
    private $publicationDate;

    // If not set, the Doctrine mapping is automatically configured with OneToOne self-referenced association nullable
    private $originalResource;

    // If not set, the Doctrine mapping is automatically configured with OneToOne self-referenced reverse association
    private $newResource;

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
        return $this->originalResource;
    }

    public function setOriginalResource(?self $originalResource): self
    {
        $this->originalResource = $originalResource;

        return $this;
    }

    public function getNewResource(): ?self
    {
        return $this->newResource;
    }

    public function setNewResource(?self $newResource): self
    {
        $this->newResource = $newResource;

        return $this;
    }
```

Configure the security with expression language, for users who have access to publishable resources:

```yaml
# config/packages/silverback_api_components.yaml:
silverback_api_components:
    # ...
    publishable:
        permission: "is_granted('ROLE_ADMIN')" # default value
```

By default, default validators are applied on save, even for draft objects. If you want to apply validators only for
published resource, configure it as following:

```php
use Silverback\ApiComponentsBundle\Annotation as Silverback;
use Silverback\ApiComponentsBundle\Entity\Utility\PublishableTrait;

#[Silverback\Publishable]
class Foo
{
    use PublishableTrait;

    /**
     * This constraint will be applied on draft and published resources.
     */
    #[Assert\NotBlank]
    public string $name = '';

    /**
     * This constraint will be applied on published resources only.
     */
    #[Assert\NotBlank(groups: ['Foo:published'])]
    public string $description = '';
}
```

You can define a custom validation group for published resources:

```php
use Silverback\ApiComponentsBundle\Annotation as Silverback;
use Silverback\ApiComponentsBundle\Entity\Utility\PublishableTrait;

#[Silverback\Publishable(validationGroups: ['custom_validation_group'])]
class Foo
{
    use PublishableTrait;

    /**
     * This constraint will be applied on draft and published resources.
     */
    #[Assert\NotBlank]
    public string $name = '';

    /**
     * This constraint will be applied on published resources only.
     * The "Foo:published" validation does not exist anymore.
     */
    #[Assert\NotBlank(groups: ['custom_validation_group'])]
    public string $description = '';
}
```
