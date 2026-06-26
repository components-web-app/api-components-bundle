<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Command;

use Doctrine\Persistence\ManagerRegistry;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractPageData;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentGroup;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentPosition;
use Silverback\ApiComponentsBundle\Entity\Core\Layout;
use Silverback\ApiComponentsBundle\Entity\Core\Page;
use Silverback\ApiComponentsBundle\Entity\Core\Route;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class GenerateFixturesCommand extends Command
{
    private array $useClasses;
    private int $compCounter;

    public function __construct(private readonly ManagerRegistry $registry)
    {
        parent::__construct();
    }

    public static function getDefaultName(): string
    {
        return 'silverback:api-components:generate-fixtures';
    }

    public function getDescription(): string
    {
        return 'Walk the database and output an AbstractCwaScaffold-compatible PHP fixture class';
    }

    protected function configure(): void
    {
        $this->addOption('output', 'o', InputOption::VALUE_REQUIRED, 'Output file path', 'src/DataFixtures/GeneratedScaffold.php');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var string $outputPath */
        $outputPath = $input->getOption('output');

        $this->useClasses = [];
        $this->compCounter = 0;

        $layouts = $this->registry->getRepository(Layout::class)->findAll();
        $pages = $this->registry->getRepository(Page::class)->findAll();
        $allPageData = $this->registry->getRepository(AbstractPageData::class)->findAll();

        $childPagesByParent = [];
        foreach ($pages as $page) {
            $parent = $page->getParentPageData() ?? $page->getParentPage();
            if (null !== $parent) {
                $childPagesByParent[spl_object_id($parent)][] = $page;
            }
        }

        $childPageDataByParent = [];
        foreach ($allPageData as $pd) {
            $parent = $pd->getParentPageData() ?? $pd->getParentPage();
            if (null !== $parent) {
                $childPageDataByParent[spl_object_id($parent)][] = $pd;
            }
        }

        $body = '';

        foreach ($layouts as $layout) {
            $body .= $this->emitLayout($layout);
        }

        foreach ($pages as $page) {
            if (null !== $page->getParentPage() || null !== $page->getParentPageData()) {
                continue;
            }
            $body .= $this->emitPage($page, $childPagesByParent, $childPageDataByParent);
        }

        foreach ($allPageData as $pd) {
            if (null !== $pd->getParentPage() || null !== $pd->getParentPageData()) {
                continue;
            }
            $body .= $this->emitPageData($pd, $childPagesByParent, $childPageDataByParent);
        }

        file_put_contents($outputPath, $this->buildFile($body));

        $output->writeln(\sprintf('<info>Fixture class written to %s</info>', $outputPath));

        return Command::SUCCESS;
    }

    private function emitLayout(Layout $layout): string
    {
        $varName = '$layout_' . $this->toVar($layout->reference ?? 'layout');
        $ref = var_export($layout->reference, true);
        $ui = var_export($this->stripUiPrefix($layout->uiComponent, 'CwaLayout'), true);

        $extra = '';
        if (null !== $layout->uiClassNames) {
            $extra .= ', uiClassNames: ' . $this->exportArray($layout->uiClassNames);
        }

        $code = "        {$varName} = \$cwa->layout({$ref}, {$ui}{$extra});\n";

        foreach ($layout->getComponentGroups() as $group) {
            $code .= $this->emitGroupCall($group, $varName, '        ');
        }

        return $code;
    }

    private function emitGroupCall(ComponentGroup $group, string $ownerVar, string $indent): string
    {
        $groupName = $this->extractGroupName($group);
        $extra = '';
        if (!empty($group->allowedComponents)) {
            $extra .= ', allow: ' . $this->exportArray($group->allowedComponents);
        }

        $positions = $group->componentPositions;
        if ($positions->isEmpty()) {
            return \sprintf("%s%s->group(%s%s);\n", $indent, $ownerVar, var_export($groupName, true), $extra);
        }

        $posCode = '';
        foreach ($positions as $position) {
            $posCode .= $this->emitPosition($position, $indent . '    ');
        }

        return \sprintf(
            "%s%s->group(%s%s, function (GroupBuilder \$g): void {\n%s%s});\n",
            $indent,
            $ownerVar,
            var_export($groupName, true),
            $extra,
            $posCode,
            $indent
        );
    }

    private function emitPosition(ComponentPosition $position, string $indent): string
    {
        if (null !== $position->pageDataProperty) {
            return \sprintf(
                "%s\$g->pageDataPosition(%s, %s);\n",
                $indent,
                var_export($position->pageDataClass, true),
                var_export($position->pageDataProperty, true)
            );
        }

        $component = $position->component;
        if (null === $component) {
            return '';
        }

        $fqcn = $component::class;
        $shortName = (new \ReflectionClass($component))->getShortName();
        $this->addUseClass($fqcn);

        $varName = '$comp' . (++$this->compCounter);
        $code = "{$indent}{$varName} = new {$shortName}();\n";

        if (null !== $component->uiComponent) {
            $code .= "{$indent}{$varName}->uiComponent = " . var_export($component->uiComponent, true) . ";\n";
        }
        if (null !== $component->uiClassNames) {
            $code .= "{$indent}{$varName}->uiClassNames = " . $this->exportArray($component->uiClassNames) . ";\n";
        }

        $rf = new \ReflectionClass($component);
        foreach ($rf->getProperties(\ReflectionProperty::IS_PUBLIC) as $prop) {
            if ($prop->getDeclaringClass()->getName() !== $fqcn) {
                continue;
            }
            $value = $prop->getValue($component);
            if (null === $value) {
                continue;
            }
            $code .= "{$indent}{$varName}->{$prop->getName()} = " . var_export($value, true) . ";\n";
        }

        $code .= "{$indent}\$g->add({$varName});\n";

        return $code;
    }

    private function emitPage(
        Page $page,
        array $childPagesByParent,
        array $childPageDataByParent,
        string $indent = '        ',
        string $builderVar = '$cwa',
    ): string {
        $ref = var_export($page->reference, true);
        $ui = var_export($this->stripUiPrefix($page->uiComponent, 'CwaPage'), true);
        $layoutRef = var_export(isset($page->layout) ? $page->layout->reference : null, true);

        $args = "{$ref}, {$ui}, layout: {$layoutRef}";

        $route = $page->getRoute();
        if (null !== $route) {
            $args .= ', route: ' . var_export($route->getPath(), true);
            $routeName = $this->getRouteName($route);
            if (null !== $routeName) {
                $args .= ', routeName: ' . var_export($routeName, true);
            }
        }

        if ($page->isTemplate) {
            $args .= ', isTemplate: true';
        }

        if (null !== $page->uiClassNames) {
            $args .= ', uiClassNames: ' . $this->exportArray($page->uiClassNames);
        }

        $groups = $page->getComponentGroups();
        $childPages = $childPagesByParent[spl_object_id($page)] ?? [];
        $childPd = $childPageDataByParent[spl_object_id($page)] ?? [];
        $hasChildren = !empty($childPages) || !empty($childPd);
        $title = $page->getTitle();

        if ($groups->isEmpty() && !$hasChildren && null === $title) {
            return "{$indent}{$builderVar}->page({$args});\n";
        }

        $configureLines = '';
        if (null !== $title) {
            $configureLines .= "{$indent}        \$page->title(" . var_export($title, true) . ");\n";
        }
        foreach ($groups as $group) {
            $configureLines .= $this->emitGroupCall($group, '$page', $indent . '        ');
        }
        if ($hasChildren) {
            $configureLines .= "{$indent}        \$page->nested(function (CwaFixtureBuilder \$child) use (\$cwa): void {\n";
            foreach ($childPages as $child) {
                $configureLines .= $this->emitPage($child, $childPagesByParent, $childPageDataByParent, $indent . '            ', '$child');
            }
            foreach ($childPd as $child) {
                $configureLines .= $this->emitPageData($child, $childPagesByParent, $childPageDataByParent, $indent . '            ', '$child');
            }
            $configureLines .= "{$indent}        });\n";
        }

        return <<<CODE
            {$indent}{$builderVar}->page({$args},
            {$indent}    configure: function (PageBuilder \$page) use (\$cwa): void {
            {$configureLines}{$indent}    }
            {$indent});
            CODE . "\n";
    }

    private function emitPageData(
        AbstractPageData $pd,
        array $childPagesByParent,
        array $childPageDataByParent,
        string $indent = '        ',
        string $builderVar = '$cwa',
    ): string {
        $fqcn = $pd::class;
        $shortName = (new \ReflectionClass($pd))->getShortName();
        $this->addUseClass($fqcn);

        $varName = '$pd_' . $this->toVar($pd->getTitle() ?? 'pageData');
        $code = "{$indent}{$varName} = new {$shortName}();\n";

        $title = $pd->getTitle();
        if (null !== $title) {
            $code .= "{$indent}{$varName}->setTitle(" . var_export($title, true) . ");\n";
        }

        $rf = new \ReflectionClass($pd);
        foreach ($rf->getProperties(\ReflectionProperty::IS_PUBLIC) as $prop) {
            if ($prop->getDeclaringClass()->getName() !== $fqcn) {
                continue;
            }
            if (!$prop->isInitialized($pd)) {
                continue;
            }
            $value = $prop->getValue($pd);
            if (null === $value) {
                continue;
            }
            $code .= "{$indent}{$varName}->{$prop->getName()} = " . var_export($value, true) . ";\n";
        }

        $templateRef = null;
        if (isset($pd->page)) {
            $templateRef = $pd->page->reference ?? null;
        }

        $route = $pd->getRoute();

        $pdArgs = $varName;
        if (null !== $templateRef) {
            $pdArgs .= ', template: ' . var_export($templateRef, true);
        }
        if (null !== $route) {
            $pdArgs .= ', route: ' . var_export($route->getPath(), true);
            $routeName = $this->getRouteName($route);
            if (null !== $routeName) {
                $pdArgs .= ', routeName: ' . var_export($routeName, true);
            }
        }

        $childPages = $childPagesByParent[spl_object_id($pd)] ?? [];
        $childPdItems = $childPageDataByParent[spl_object_id($pd)] ?? [];
        $hasChildren = !empty($childPages) || !empty($childPdItems);

        if (!$hasChildren) {
            $code .= "{$indent}{$builderVar}->pageData({$pdArgs});\n";
        } else {
            $code .= "{$indent}{$builderVar}->pageData({$pdArgs})\n";
            $code .= "{$indent}    ->nested(function (CwaFixtureBuilder \$child) use (\$cwa): void {\n";
            foreach ($childPages as $child) {
                $code .= $this->emitPage($child, $childPagesByParent, $childPageDataByParent, $indent . '        ', '$child');
            }
            foreach ($childPdItems as $child) {
                $code .= $this->emitPageData($child, $childPagesByParent, $childPageDataByParent, $indent . '        ', '$child');
            }
            $code .= "{$indent}    });\n";
        }

        return $code;
    }

    private function buildFile(string $body): string
    {
        $coreUses = implode("\n", [
            'use Silverback\\ApiComponentsBundle\\Fixture\\AbstractCwaScaffold;',
            'use Silverback\\ApiComponentsBundle\\Fixture\\Builder\\GroupBuilder;',
            'use Silverback\\ApiComponentsBundle\\Fixture\\Builder\\PageBuilder;',
            'use Silverback\\ApiComponentsBundle\\Fixture\\CwaFixtureBuilder;',
        ]);

        $extraUses = '';
        if (!empty($this->useClasses)) {
            sort($this->useClasses);
            $extraUses = "\n" . implode("\n", array_map(static fn ($c) => "use {$c};", $this->useClasses));
        }

        return <<<PHP
            <?php

            namespace App\\DataFixtures;

            {$coreUses}{$extraUses}

            class GeneratedScaffold extends AbstractCwaScaffold
            {
                public function build(CwaFixtureBuilder \$cwa): void
                {
            {$body}    }
            }
            PHP;
    }

    private function addUseClass(string $fqcn): void
    {
        if (!\in_array($fqcn, $this->useClasses, true)) {
            $this->useClasses[] = $fqcn;
        }
    }

    private function extractGroupName(ComponentGroup $group): string
    {
        $reference = $group->reference ?? '';
        $location = $group->location ?? '';
        if ('' !== $location && str_contains($reference, '_' . $location)) {
            $name = substr($reference, 0, strpos($reference, '_' . $location));
            if (false !== $name && '' !== $name) {
                return $name;
            }
        }

        return $reference;
    }

    private function toVar(string $str): string
    {
        return preg_replace('/[^a-zA-Z0-9_]/', '_', $str);
    }

    private function exportArray(array $arr): string
    {
        $items = implode(', ', array_map(static fn ($v) => var_export($v, true), $arr));

        return '[' . $items . ']';
    }

    private function stripUiPrefix(?string $value, string $prefix): ?string
    {
        if (null === $value) {
            return null;
        }

        return str_starts_with($value, $prefix) ? substr($value, \strlen($prefix)) : $value;
    }

    private function getRouteName(Route $route): ?string
    {
        $rp = (new \ReflectionClass($route))->getProperty('name');

        return $rp->isInitialized($route) ? $route->getName() : null;
    }
}
