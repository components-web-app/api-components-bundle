---
layout: default
title: Publishable
parent: Custom Components
nav_order: 1
---
# Publishable

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
