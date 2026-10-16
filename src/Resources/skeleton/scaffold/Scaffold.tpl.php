<?php echo "<?php\n"; ?>

namespace <?php echo $namespace; ?>;

<?php echo $use_statements; ?>

class <?php echo $class_name; ?> extends AbstractCwaScaffold
{
    public function build(CwaFixtureBuilder $cwa): void
    {
        $this-><?php echo $layout_method; ?>($cwa);
        $this->homePage($cwa);
    }

    private function <?php echo $layout_method; ?>(CwaFixtureBuilder $cwa): void
    {
<?php if (null !== $link_class) { ?>
        $<?php echo $link_variable; ?> = new <?php echo $link_class; ?>();
        $<?php echo $link_variable; ?><?php echo $link_label; ?>;
<?php if ($publish_link) { ?>
        $cwa->publish($<?php echo $link_variable; ?>);
<?php } ?>
        $cwa->component($<?php echo $link_variable; ?>)->linkRoute('route', routeName: 'home');

        $cwa->layout(<?php echo var_export($layout_ref, true); ?>, uiComponent: <?php echo var_export($layout_component, true); ?>)
            ->group('header')
            ->add($<?php echo $link_variable; ?>);
<?php } else { ?>
        $cwa->layout(<?php echo var_export($layout_ref, true); ?>, uiComponent: <?php echo var_export($layout_component, true); ?>)
            ->group('header');
<?php } ?>
    }

    private function homePage(CwaFixtureBuilder $cwa): void
    {
        $cwa->page('home', uiComponent: 'Primary', layout: <?php echo var_export($layout_ref, true); ?>, route: '/', routeName: 'home')
            ->title('Home')
            ->group('primary');
    }
}
