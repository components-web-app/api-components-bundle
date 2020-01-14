<?php

/*
 * This file is part of the Silverback API Component Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Silverback\ApiComponentBundle\Entity\Core;

use ApiPlatform\Core\Annotation\ApiResource;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @author Daniel West <daniel@silverback.is>
 * @ORM\MappedSuperclass
 */
abstract class AbstractPageData extends AbstractPage implements PageDataInterface
{
    /*
     * Extend this class for pages where the same page template should be used for multiple entities.
     * A good example is an article page. You would create an Article entity in your project that extends this class.
     * That article can then be accessed via a route on the API and the data in this class will override whatever is in the template.
     * You can create a ComponentPopulator service to use the data provided here to populate the template. You could update text
     * within entities with interpolation, or add new components on the fly depending on what you have defined here. It is a very flexible
     * way of generating different layouts depending on the data in your entity.
     */

    /**
     * @ORM\ManyToOne(targetEntity="Silverback\ApiComponentBundle\Entity\Core\Route", inversedBy="pageData", cascade={"persist"})
     *
     * @var Route
     */
    public Route $routes;
}
