<?php echo "<?php\n"; ?>

namespace <?php echo $namespace; ?>;

<?php echo $use_statements; ?>
/**
 * CWA site scaffold — wire up layouts, pages, and nav links here.
 *
 * Phase ordering (handled automatically by CwaFixtureBuilder::flush()):
 *   1. Entities persisted (layouts, pages, page data)
 *   2. Component groups created
 *   3. Routes generated (parents before children)
 *   4. Component positions and nav links created
 *
 * Nav links reference routes, so add them AFTER $cwa->page() / $cwa->pageData() calls.
 */
class <?php echo $class_name; ?> extends AbstractCwaScaffold
{
    public function build(CwaFixtureBuilder $cwa): void
    {
        // --- Layout -----------------------------------------------------------
        // The nav group is populated below once routes exist.
        $navGroup = $cwa->layout('<?php echo $layout_ref; ?>', '<?php echo $layout_component; ?>')
            ->group('top');

        // --- Pages ------------------------------------------------------------
        $cwa->page('home', 'PrimaryPage', layout: '<?php echo $layout_ref; ?>', route: '/', routeName: 'home-page',
            configure: fn (PageBuilder $page) => $page
                ->title('Home')
                ->group('primary')
        );

        // Add more pages here, e.g.:
        // $cwa->page('about', 'PrimaryPage', layout: '<?php echo $layout_ref; ?>', route: '/about', routeName: 'about-page',
        //     configure: fn (PageBuilder $page) => $page->title('About')->group('primary')
        // );

        // --- Nav links (added after routes exist) -----------------------------
        // Replace NavigationLink with your nav component class, e.g.:
        // $navGroup->add(
        //     (new NavigationLink())
        //         ->setLabel('Home')
        //         ->setRoute($cwa->getRoute('home-page'))
        //         ->setPublishedAt(new \DateTime())
        // );
    }
}
