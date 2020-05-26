---
layout: default
title: Timestamped
parent: Component Annotations
nav_order: 2
---
# Timestamped

To set an entity component as timestamped, use the following annotation and trait:

```php
use Silverback\ApiComponentsBundle\Annotation as Silverback;
use Silverback\ApiComponentsBundle\Entity\Utility\TimestampedTrait;

/**
 * @Silverback\Timestamped
 */
class Foo
{
    use TimestampedTrait;
```

Default field for the created timestamp is`createdAt` and the modified timestamp `modifiedAt`. To customize these, update the annotation and your class:

```php
use Silverback\ApiComponentsBundle\Annotation as Silverback;

/**
 * @Silverback\Timestamped(createdAtField="customCreatedAt", modifiedAtField="customModifiedAt")
 */
class Foo
{
    // If not set, the Doctrine mapping is automatically configured with type="datetime_immutable" not nullable
    private ?DateTimeImmutable $customCreatedAt;

    // If not set, the Doctrine mapping is automatically configured with type="datetime" not nullable
    private ?DateTime $customModifiedAt;

    public function setCustomCreatedAt(DateTimeImmutable $customCreatedAt): self
    {
        if (!$this->customCreatedAt) {
            $this->customCreatedAt = $customCreatedAt;
        }

        return $this;
    }

    public function getCustomCreatedAt(): ?DateTimeImmutable
    {
        return $this->customCreatedAt;
    }

    public function setCustomModifiedAt(DateTime $customModifiedAt): self
    {
        $this->customModifiedAt = $customModifiedAt;

        return $this;
    }

    public function getCustomModifiedAt(): ?DateTime
    {
        return $this->customModifiedAt;
    }
```
