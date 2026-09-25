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

use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\IriConverterInterface;
use ApiPlatform\Metadata\UrlGeneratorInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\Persistence\ManagerRegistry;
use League\Flysystem\FilesystemException;
use Silverback\ApiComponentsBundle\AttributeReader\PublishableAttributeReader;
use Silverback\ApiComponentsBundle\AttributeReader\TimestampedAttributeReader;
use Silverback\ApiComponentsBundle\AttributeReader\UploadableAttributeReaderInterface;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractComponent;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractPage;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractPageData;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentGroup;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentPosition;
use Silverback\ApiComponentsBundle\Entity\Core\Layout;
use Silverback\ApiComponentsBundle\Entity\Core\Page;
use Silverback\ApiComponentsBundle\Entity\Core\Route;
use Silverback\ApiComponentsBundle\Flysystem\FilesystemProvider;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpFoundation\File\File;

#[AsCommand(
    name: 'silverback:api-components:generate-fixtures',
    description: 'Walk the database and output an AbstractCwaScaffold-compatible PHP fixture class',
)]
class GenerateFixturesCommand extends Command
{
    private const string INDENT = '        ';
    private const string CLOSURE_USE = 'use ($cwa, &$c, &$g)';
    private const string IDENTIFIER = '[a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff]*';

    private array $useClasses = [];
    private int $componentCounter = 0;
    private int $groupCounter = 0;

    /** @var array<int, string> */
    private array $componentVars = [];

    /** @var array<int, AbstractComponent> */
    private array $emittedComponents = [];

    /** @var array<int, true> */
    private array $emittedGroups = [];

    /** @var array<string, true> */
    private array $emittedRouteNames = [];

    /** @var list<array{var: string, entity: object, property: string, target: object}> */
    private array $deferredReferences = [];

    /** @var list<string> */
    private array $notReproduced = [];

    /** @var array<string, string>|null */
    private ?array $componentClassesByIri = null;

    private string $assetsDirectory = '';

    /** @var array<string, true> */
    private array $assetNames = [];

    public function __construct(
        private readonly ManagerRegistry $registry,
        private readonly IriConverterInterface $iriConverter,
        private readonly UploadableAttributeReaderInterface $uploadableAttributeReader,
        private readonly PublishableAttributeReader $publishableAttributeReader,
        private readonly TimestampedAttributeReader $timestampedAttributeReader,
        private readonly FilesystemProvider $filesystemProvider,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('output', 'o', InputOption::VALUE_REQUIRED, 'Output file path; the class is named after the file', 'src/DataFixtures/GeneratedScaffold.php');
        $this->addOption('namespace', null, InputOption::VALUE_REQUIRED, 'Namespace of the generated class', 'App\\DataFixtures');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var string $outputPath */
        $outputPath = $input->getOption('output');
        $className = pathinfo($outputPath, \PATHINFO_FILENAME);
        if (1 !== preg_match('/^' . self::IDENTIFIER . '$/', $className)) {
            $output->writeln(\sprintf('<error>The output file name "%s" is not a valid PHP class name, so the class could not be named after it.</error>', $className));

            return Command::FAILURE;
        }
        $namespace = trim((string) $input->getOption('namespace'), '\\');
        if (1 !== preg_match('/^' . self::IDENTIFIER . '(\\\\' . self::IDENTIFIER . ')*$/', $namespace)) {
            $output->writeln(\sprintf('<error>"%s" is not a valid PHP namespace.</error>', $namespace));

            return Command::FAILURE;
        }

        $this->resetState(\dirname($outputPath) . '/assets');

        $layouts = $this->registry->getRepository(Layout::class)->findAll();
        $pages = $this->registry->getRepository(Page::class)->findAll();
        $allPageData = $this->registry->getRepository(AbstractPageData::class)->findAll();
        $routes = $this->registry->getRepository(Route::class)->findAll();

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

        $body = self::INDENT . "\$c = [];\n" . self::INDENT . "\$g = [];\n";

        foreach ($layouts as $layout) {
            $body .= $this->emitLayout($layout);
        }

        foreach ($pages as $page) {
            if (null !== $page->getParentPage() || null !== $page->getParentPageData()) {
                continue;
            }
            $body .= $this->emitPage($page, $childPagesByParent, $childPageDataByParent, self::INDENT, '$cwa');
        }

        foreach ($allPageData as $pd) {
            if (null !== $pd->getParentPage() || null !== $pd->getParentPageData()) {
                continue;
            }
            $body .= $this->emitPageData($pd, $childPagesByParent, $childPageDataByParent, self::INDENT, '$cwa');
        }

        $body .= $this->emitRedirects($routes);
        $body .= $this->emitAfterRoutes();

        file_put_contents($outputPath, $this->buildFile($body, $namespace, $className));

        $output->writeln(\sprintf('<info>Fixture class written to %s</info>', $outputPath));
        if ([] !== $this->assetNames) {
            $output->writeln(\sprintf('<info>%d stored file(s) exported to %s</info>', \count($this->assetNames), $this->assetsDirectory));
        }
        if ([] !== $this->notReproduced) {
            $output->writeln(\sprintf('<comment>%d item(s) could not be reproduced:</comment>', \count($this->notReproduced)));
            foreach ($this->notReproduced as $message) {
                $output->writeln('  - ' . $message);
            }
        }

        return Command::SUCCESS;
    }

    private function resetState(string $assetsDirectory): void
    {
        $this->useClasses = [];
        $this->componentCounter = 0;
        $this->groupCounter = 0;
        $this->componentVars = [];
        $this->emittedComponents = [];
        $this->emittedGroups = [];
        $this->emittedRouteNames = [];
        $this->deferredReferences = [];
        $this->notReproduced = [];
        $this->componentClassesByIri = null;
        $this->assetsDirectory = $assetsDirectory;
        $this->assetNames = [];
    }

    private function emitLayout(Layout $layout): string
    {
        $args = var_export($layout->reference, true) . ', ' . var_export($this->stripUiPrefix($layout->uiComponent, 'CwaLayout') ?? '', true);
        if (null !== $layout->uiClassNames) {
            $args .= ', uiClassNames: ' . $this->exportArray($layout->uiClassNames);
        }

        $code = self::INDENT . "\$layout = \$cwa->layout({$args});\n";
        foreach ($layout->getComponentGroups() as $group) {
            $code .= $this->emitGroup($group, '$layout', $layout, self::INDENT);
        }

        return $code;
    }

    private function emitGroup(ComponentGroup $group, string $ownerExpr, object $owner, string $indent): string
    {
        $reference = (string) $group->reference;
        $ownerIri = $this->iriConverter->getIriFromResource($owner);
        $locationSuffix = '_' . $group->location;

        $locationReference = null;
        if (null !== $group->location && str_ends_with($reference, $locationSuffix) && \strlen($reference) > \strlen($locationSuffix)) {
            if ($group->location !== $ownerIri) {
                $this->notReproduced[] = \sprintf('Component group "%s" is also used by %s, which the fixture builder cannot share it with.', $reference, $ownerIri);

                return '';
            }
            $name = substr($reference, 0, -\strlen($locationSuffix));
        } elseif (str_contains($reference, '_')) {
            [$name, $locationReference] = explode('_', $reference, 2);
        } else {
            $this->notReproduced[] = \sprintf('Component group "%s" on %s has a reference the fixture builder cannot express.', $reference, $ownerIri);

            return '';
        }

        $args = var_export($name, true);
        if (null !== $locationReference) {
            $args .= ', locationReference: ' . var_export($locationReference, true);
        }
        $allowed = $this->resolveAllowedClasses($group);
        if ([] !== $allowed) {
            $args .= ', allow: [' . implode(', ', $allowed) . ']';
        }

        $groupVar = '$g[' . (++$this->groupCounter) . ']';
        $code = "{$indent}{$groupVar} = {$ownerExpr}->group({$args});\n";

        $groupId = spl_object_id($group);
        if (isset($this->emittedGroups[$groupId])) {
            return $code;
        }
        $this->emittedGroups[$groupId] = true;

        $positions = $group->componentPositions->toArray();
        usort($positions, static fn (ComponentPosition $a, ComponentPosition $b) => $a->sortValue <=> $b->sortValue);
        foreach ($positions as $position) {
            $code .= $this->emitPosition($position, $groupVar, $indent);
        }

        return $code;
    }

    /**
     * @return list<string>
     */
    private function resolveAllowedClasses(ComponentGroup $group): array
    {
        $allowed = [];
        foreach ($group->allowedComponents ?? [] as $iri) {
            $class = $this->getComponentClassesByIri()[$iri] ?? null;
            if (null === $class) {
                $this->notReproduced[] = \sprintf('Allowed component "%s" on the component group "%s" does not match a component class.', $iri, $group->reference);
                continue;
            }
            $allowed[] = $this->classReference($class);
        }

        return $allowed;
    }

    /**
     * @return array<string, string>
     */
    private function getComponentClassesByIri(): array
    {
        if (null !== $this->componentClassesByIri) {
            return $this->componentClassesByIri;
        }

        $this->componentClassesByIri = [];
        $manager = $this->registry->getManagerForClass(AbstractComponent::class);
        foreach ($manager?->getMetadataFactory()->getAllMetadata() ?? [] as $metadata) {
            $class = $metadata->getName();
            if (!is_a($class, AbstractComponent::class, true) || (new \ReflectionClass($class))->isAbstract()) {
                continue;
            }
            try {
                $iri = $this->iriConverter->getIriFromResource($class, UrlGeneratorInterface::ABS_PATH, (new GetCollection())->withClass($class));
            } catch (\Throwable) {
                continue;
            }
            if (null !== $iri && !str_starts_with($iri, '/.well-known/genid/')) {
                $this->componentClassesByIri[$iri] = $class;
            }
        }

        return $this->componentClassesByIri;
    }

    private function emitPosition(ComponentPosition $position, string $groupVar, string $indent): string
    {
        if (null !== $position->pageDataProperty) {
            if (null !== $position->component) {
                $this->notReproduced[] = \sprintf('The fallback component of the page data position "%s" in the component group "%s".', $position->pageDataProperty, $position->componentGroup?->reference);
            }

            return \sprintf(
                "%s%s->pageDataPosition(%s, %s);\n",
                $indent,
                $groupVar,
                null === $position->pageDataClass ? 'null' : $this->classReference($position->pageDataClass),
                var_export($position->pageDataProperty, true)
            );
        }

        $component = $position->component;
        if (null === $component) {
            return '';
        }

        $id = spl_object_id($component);
        if (isset($this->componentVars[$id])) {
            return "{$indent}{$groupVar}->add({$this->componentVars[$id]});\n";
        }

        $code = $this->emitComponent($component, $indent);
        $var = $this->componentVars[$id];
        $code .= "{$indent}{$groupVar}->add({$var});\n";

        foreach ($component->getComponentGroups() as $ownedGroup) {
            $code .= $this->emitGroup($ownedGroup, "\$cwa->component({$var})", $component, $indent);
        }

        return $code;
    }

    private function emitComponent(object $component, string $indent): string
    {
        $var = '$c[' . (++$this->componentCounter) . ']';
        $this->componentVars[spl_object_id($component)] = $var;
        if ($component instanceof AbstractComponent) {
            $this->emittedComponents[spl_object_id($component)] = $component;
        }

        $code = "{$indent}{$var} = new {$this->shortName($component)}();\n";
        $handledAssociations = ['componentGroups', 'componentPositions'];
        if ($this->publishableAttributeReader->isConfigured($component)) {
            $configuration = $this->publishableAttributeReader->getConfiguration($component);
            $handledAssociations[] = $configuration->reverseAssociationName;
        }

        return $code . $this->emitEntityState($component, $var, $indent, $handledAssociations, false);
    }

    /**
     * @param list<string> $handledAssociations
     */
    private function emitEntityState(object $entity, string $var, string $indent, array $handledAssociations, bool $inlineComponents): string
    {
        $this->registry->getManagerForClass($entity::class)->initializeObject($entity);
        $metadata = $this->getClassMetadata($entity);
        $code = '';

        $skippedFields = [];
        if ($this->timestampedAttributeReader->isConfigured($entity)) {
            $configuration = $this->timestampedAttributeReader->getConfiguration($entity);
            $skippedFields[] = $configuration->createdAtField;
            $skippedFields[] = $configuration->modifiedAtField;
        }
        $uploadableFields = [];
        if ($this->uploadableAttributeReader->isConfigured($entity)) {
            foreach ($this->uploadableAttributeReader->getConfiguredProperties($entity, true) as $fileProperty => $fieldConfiguration) {
                $uploadableFields[$fileProperty] = $fieldConfiguration;
                $skippedFields[] = $fieldConfiguration->property;
            }
        }

        foreach ($metadata->getFieldNames() as $field) {
            if ($metadata->isIdentifier($field) || \in_array($field, $skippedFields, true)) {
                continue;
            }
            $value = $metadata->getFieldValue($entity, $field);
            if ($this->isDefaultValue($metadata, $field, $value)) {
                continue;
            }
            $expression = $this->exportValue($value);
            if (null === $expression) {
                $this->notReproduced[] = \sprintf('The value of %s::$%s cannot be written as PHP.', $metadata->getName(), $field);
                continue;
            }
            $code .= $this->writeStatement($var, $entity, $field, $expression, $indent);
        }

        foreach ($uploadableFields as $fileProperty => $fieldConfiguration) {
            $storedPath = $metadata->getFieldValue($entity, $fieldConfiguration->property);
            if (null === $storedPath) {
                continue;
            }
            $assetName = $this->exportStoredFile($fieldConfiguration->adapter, $storedPath);
            if (null === $assetName) {
                continue;
            }
            $this->addUseClass(File::class);
            $code .= $this->writeStatement($var, $entity, $fileProperty, "new File(__DIR__ . '/assets/{$assetName}')", $indent);
        }

        foreach ($metadata->getAssociationNames() as $association) {
            if (\in_array($association, $handledAssociations, true) || $metadata->isAssociationInverseSide($association)) {
                continue;
            }
            $value = $metadata->getFieldValue($entity, $association);
            if (null === $value) {
                continue;
            }
            if (is_iterable($value)) {
                if (\count($value) > 0) {
                    $this->notReproduced[] = \sprintf('The collection %s::$%s.', $metadata->getName(), $association);
                }
                continue;
            }
            if ($inlineComponents && $value instanceof AbstractComponent && !isset($this->componentVars[spl_object_id($value)])) {
                $code .= $this->emitComponent($value, $indent);
            }
            if ($inlineComponents && $value instanceof AbstractComponent) {
                $code .= $this->writeStatement($var, $entity, $association, $this->componentVars[spl_object_id($value)], $indent);
                continue;
            }
            $this->deferredReferences[] = ['var' => $var, 'entity' => $entity, 'property' => $association, 'target' => $value];
        }

        return $code;
    }

    private function isDefaultValue(ClassMetadata $metadata, string $field, mixed $value): bool
    {
        for ($class = new \ReflectionClass($metadata->getName()); false !== $class; $class = $class->getParentClass()) {
            if ($class->hasProperty($field)) {
                $property = $class->getProperty($field);

                return $property->hasDefaultValue() ? $value === $property->getDefaultValue() : null === $value;
            }
        }

        return null === $value;
    }

    private function exportValue(mixed $value): ?string
    {
        if (null === $value || \is_scalar($value)) {
            return var_export($value, true);
        }
        if ($value instanceof \DateTimeImmutable) {
            return \sprintf("new \\DateTimeImmutable('%s')", $value->format('Y-m-d\TH:i:s.uP'));
        }
        if ($value instanceof \DateTimeInterface) {
            return \sprintf("new \\DateTime('%s')", $value->format('Y-m-d\TH:i:s.uP'));
        }
        if ($value instanceof \UnitEnum) {
            return '\\' . $value::class . '::' . $value->name;
        }
        if (\is_array($value)) {
            $items = [];
            foreach ($value as $key => $item) {
                $exported = $this->exportValue($item);
                if (null === $exported) {
                    return null;
                }
                $items[] = var_export($key, true) . ' => ' . $exported;
            }

            return '[' . implode(', ', $items) . ']';
        }

        return null;
    }

    private function writeStatement(string $var, object $entity, string $property, string $expression, string $indent): string
    {
        $reflection = new \ReflectionClass($entity);
        if ($reflection->hasProperty($property)) {
            $reflectionProperty = $reflection->getProperty($property);
            if ($reflectionProperty->isPublic() && !$reflectionProperty->isReadOnly()) {
                return "{$indent}{$var}->{$property} = {$expression};\n";
            }
        }
        $setter = 'set' . ucfirst($property);
        if ($reflection->hasMethod($setter) && $reflection->getMethod($setter)->isPublic()) {
            return "{$indent}{$var}->{$setter}({$expression});\n";
        }

        $this->notReproduced[] = \sprintf('%s::$%s has no public property or setter to write it with.', $entity::class, $property);

        return '';
    }

    private function exportStoredFile(string $adapter, string $storedPath): ?string
    {
        try {
            $contents = $this->filesystemProvider->getFilesystem($adapter)->read($storedPath);
        } catch (FilesystemException $exception) {
            $this->notReproduced[] = \sprintf('The stored file "%s" could not be read: %s', $storedPath, $exception->getMessage());

            return null;
        }

        $extension = pathinfo($storedPath, \PATHINFO_EXTENSION);
        $stem = preg_replace('/-[0-9a-f]{8}$/', '', pathinfo($storedPath, \PATHINFO_FILENAME)) ?: 'file';
        $suffix = '' === $extension ? '' : '.' . $extension;
        $name = $stem . $suffix;
        for ($i = 2; isset($this->assetNames[$name]); ++$i) {
            $name = $stem . '-' . $i . $suffix;
        }
        $this->assetNames[$name] = true;

        if (!is_dir($this->assetsDirectory)) {
            mkdir($this->assetsDirectory, 0777, true);
        }
        file_put_contents($this->assetsDirectory . '/' . $name, $contents);

        return $name;
    }

    private function emitPage(
        Page $page,
        array $childPagesByParent,
        array $childPageDataByParent,
        string $indent,
        string $builderVar,
    ): string {
        $args = var_export($page->reference, true)
            . ', ' . var_export($this->stripUiPrefix($page->uiComponent, 'CwaPage') ?? '', true)
            . ', layout: ' . var_export($page->layout->reference ?? '', true);
        $args .= $this->routeArguments($page->getRoute());
        if ($page->isTemplate) {
            $args .= ', isTemplate: true';
        }
        if (null !== $page->uiClassNames) {
            $args .= ', uiClassNames: ' . $this->exportArray($page->uiClassNames);
        }

        $code = "{$indent}\$page = {$builderVar}->page({$args});\n";
        if (null !== $page->getTitle()) {
            $code .= "{$indent}\$page->title(" . var_export($page->getTitle(), true) . ");\n";
        }
        if (null !== $page->getMetaDescription()) {
            $code .= "{$indent}\$page->metaDescription(" . var_export($page->getMetaDescription(), true) . ");\n";
        }
        if (null === $page->getRoute() && !$page->isTemplate) {
            $code .= "{$indent}\$page->withoutRoute();\n";
        }
        $liveAt = $this->liveAtExpression($page->getRoute());
        if (null !== $liveAt) {
            $code .= "{$indent}\$page->liveAt({$liveAt});\n";
        }
        foreach ($page->getComponentGroups() as $group) {
            $code .= $this->emitGroup($group, '$page', $page, $indent);
        }

        $nested = $this->emitChildren($page, $childPagesByParent, $childPageDataByParent, $indent . '    ');
        if ('' !== $nested) {
            $code .= "{$indent}\$page->nested(function (CwaFixtureBuilder \$child) " . self::CLOSURE_USE . ": void {\n{$nested}{$indent}});\n";
        }

        return $code;
    }

    private function emitPageData(
        AbstractPageData $pd,
        array $childPagesByParent,
        array $childPageDataByParent,
        string $indent,
        string $builderVar,
    ): string {
        $code = "{$indent}\$pageData = new {$this->shortName($pd)}();\n";
        $code .= $this->emitEntityState($pd, '$pageData', $indent, ['route', 'page', 'parentPage', 'parentPageData'], true);

        $args = '$pageData';
        if (null !== ($pd->page ?? null)) {
            $args .= ', template: ' . var_export($pd->page->reference, true);
        }
        $args .= $this->routeArguments($pd->getRoute());

        $chain = null === $pd->getRoute() ? "\n{$indent}    ->withoutRoute()" : '';
        $liveAt = $this->liveAtExpression($pd->getRoute());
        if (null !== $liveAt) {
            $chain .= "\n{$indent}    ->liveAt({$liveAt})";
        }
        $nested = $this->emitChildren($pd, $childPagesByParent, $childPageDataByParent, $indent . '        ');
        if ('' !== $nested) {
            $chain .= "\n{$indent}    ->nested(function (CwaFixtureBuilder \$child) " . self::CLOSURE_USE . ": void {\n{$nested}{$indent}    })";
        }

        return $code . "{$indent}{$builderVar}->pageData({$args}){$chain};\n";
    }

    private function emitChildren(AbstractPage $parent, array $childPagesByParent, array $childPageDataByParent, string $indent): string
    {
        $code = '';
        foreach ($childPagesByParent[spl_object_id($parent)] ?? [] as $child) {
            $code .= $this->emitPage($child, $childPagesByParent, $childPageDataByParent, $indent, '$child');
        }
        foreach ($childPageDataByParent[spl_object_id($parent)] ?? [] as $child) {
            $code .= $this->emitPageData($child, $childPagesByParent, $childPageDataByParent, $indent, '$child');
        }

        return $code;
    }

    private function routeArguments(?Route $route): string
    {
        if (null === $route) {
            return '';
        }
        $this->emittedRouteNames[$route->getName()] = true;

        return ', route: ' . var_export($route->getPath(), true) . ', routeName: ' . var_export($route->getName(), true);
    }

    private function liveAtExpression(?Route $route): ?string
    {
        if (null === $route) {
            return null;
        }
        $liveAt = $route->getLiveAt();
        if (null !== $liveAt && $liveAt <= new \DateTimeImmutable()) {
            return null;
        }

        return $this->exportValue($liveAt);
    }

    /**
     * @param Route[] $routes
     */
    private function emitRedirects(array $routes): string
    {
        $pending = array_values(array_filter($routes, static fn (Route $route) => null !== $route->getRedirect()));
        $code = '';
        while ([] !== $pending) {
            $progressed = false;
            foreach ($pending as $index => $route) {
                $target = $route->getRedirect();
                if (!isset($this->emittedRouteNames[$target->getName()])) {
                    continue;
                }
                $code .= \sprintf(
                    "%s\$cwa->redirect(%s, to: %s, name: %s);\n",
                    self::INDENT,
                    var_export($route->getPath(), true),
                    var_export($target->getName(), true),
                    var_export($route->getName(), true)
                );
                $this->emittedRouteNames[$route->getName()] = true;
                unset($pending[$index]);
                $progressed = true;
            }
            if (!$progressed) {
                foreach ($pending as $route) {
                    $this->notReproduced[] = \sprintf('The redirect from "%s" to "%s", whose target has no page.', $route->getPath(), $route->getRedirect()->getPath());
                }
                break;
            }
        }

        return $code;
    }

    private function emitAfterRoutes(): string
    {
        $indent = self::INDENT . '    ';
        $drafts = '';
        $persists = '';

        foreach ($this->emittedComponents as $component) {
            if (!$this->publishableAttributeReader->isConfigured($component)) {
                continue;
            }
            $configuration = $this->publishableAttributeReader->getConfiguration($component);
            $draft = $this->getClassMetadata($component)->getFieldValue($component, $configuration->reverseAssociationName);
            if (null === $draft || isset($this->componentVars[spl_object_id($draft)])) {
                continue;
            }
            $drafts .= $this->emitComponent($draft, $indent);
            $persists .= "{$indent}\$cwa->persist({$this->componentVars[spl_object_id($draft)]});\n";
        }

        $references = '';
        foreach ($this->deferredReferences as $reference) {
            $expression = $this->referenceExpression($reference['target']);
            if (null === $expression) {
                $this->notReproduced[] = \sprintf('%s::$%s refers to %s, which is not part of the generated site.', $reference['entity']::class, $reference['property'], $this->describe($reference['target']));
                continue;
            }
            $references .= $this->writeStatement($reference['var'], $reference['entity'], $reference['property'], $expression, $indent);
        }

        $body = $drafts . $references . $persists;
        if ('' === $body) {
            return '';
        }

        return self::INDENT . "\$cwa->afterRoutes(function (CwaFixtureBuilder \$cwa) use (&\$c): void {\n{$body}" . self::INDENT . "});\n";
    }

    private function referenceExpression(object $target): ?string
    {
        if ($target instanceof Route) {
            return isset($this->emittedRouteNames[$target->getName()]) ? '$cwa->getRoute(' . var_export($target->getName(), true) . ')' : null;
        }

        return $this->componentVars[spl_object_id($target)] ?? null;
    }

    private function describe(object $entity): string
    {
        try {
            return $this->iriConverter->getIriFromResource($entity) ?? $entity::class;
        } catch (\Throwable) {
            return $entity::class;
        }
    }

    private function getClassMetadata(object $entity): ClassMetadata
    {
        $metadata = $this->registry->getManagerForClass($entity::class)?->getClassMetadata($entity::class);
        if (!$metadata instanceof ClassMetadata) {
            throw new \LogicException(\sprintf('%s is not mapped by the Doctrine ORM.', $entity::class));
        }

        return $metadata;
    }

    private function buildFile(string $body, string $namespace, string $className): string
    {
        $coreUses = implode("\n", [
            'use Silverback\\ApiComponentsBundle\\Fixture\\AbstractCwaScaffold;',
            'use Silverback\\ApiComponentsBundle\\Fixture\\CwaFixtureBuilder;',
        ]);

        $extraUses = '';
        if ([] !== $this->useClasses) {
            sort($this->useClasses);
            $extraUses = "\n" . implode("\n", array_map(static fn ($c) => "use {$c};", $this->useClasses));
        }

        return <<<PHP
            <?php

            namespace {$namespace};

            {$coreUses}{$extraUses}

            class {$className} extends AbstractCwaScaffold
            {
                public function build(CwaFixtureBuilder \$cwa): void
                {
            {$body}    }
            }

            PHP;
    }

    private function shortName(object $entity): string
    {
        $class = $this->getClassMetadata($entity)->getName();
        $this->addUseClass($class);

        return (new \ReflectionClass($class))->getShortName();
    }

    private function classReference(string $class): string
    {
        $this->addUseClass($class);

        return (new \ReflectionClass($class))->getShortName() . '::class';
    }

    private function addUseClass(string $fqcn): void
    {
        $fqcn = ltrim($fqcn, '\\');
        if (!\in_array($fqcn, $this->useClasses, true)) {
            $this->useClasses[] = $fqcn;
        }
    }

    private function exportArray(array $arr): string
    {
        return '[' . implode(', ', array_map(static fn ($v) => var_export($v, true), $arr)) . ']';
    }

    private function stripUiPrefix(?string $value, string $prefix): ?string
    {
        if (null === $value) {
            return null;
        }

        return str_starts_with($value, $prefix) ? substr($value, \strlen($prefix)) : $value;
    }
}
