---
layout: default
nav_order: 6
---
# Custom Components

> __Incomplete Documentation__

All component resource endpoints are prefixed `/component` (e.g. `/components/forms/{id}`)

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
