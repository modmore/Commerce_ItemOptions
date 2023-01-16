<?php
namespace modmore\Commerce_ItemOptions\Modules;

use comOrderItemExtraAdjustment;
use comProduct;
use modmore\Commerce\Admin\Configuration\About\ComposerPackages;
use modmore\Commerce\Admin\Sections\SimpleSection;
use modmore\Commerce\Admin\Widgets\Form\TextField;
use modmore\Commerce\Events\Admin\PageEvent;
use modmore\Commerce\Events\Cart\Item;
use modmore\Commerce\Modules\BaseModule;
use modX;
use modmore\Commerce\Dispatcher\EventDispatcher;

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

class ItemOptions extends BaseModule {

    public function getName()
    {
        $this->adapter->loadLexicon('commerce_itemoptions:default');
        return $this->adapter->lexicon('commerce_itemoptions');
    }

    public function getAuthor()
    {
        return 'modmore';
    }

    public function getDescription()
    {
        return $this->adapter->lexicon('commerce_itemoptions.description');
    }

    public function initialize(EventDispatcher $dispatcher)
    {
        // Load our lexicon
        $this->adapter->loadLexicon('commerce_itemoptions:default');

        // Add the xPDO package, so Commerce can detect the derivative classes
//        $root = dirname(__DIR__, 2);
//        $path = $root . '/model/';
//        $this->adapter->loadPackage('commerce_itemoptions', $path);

        // Add template path to twig
//        $root = dirname(__DIR__, 2);
//        $this->commerce->view()->addTemplatesPath($root . '/templates/');

        $dispatcher->addListener(\Commerce::EVENT_ITEM_ADDED_TO_CART, [$this, 'onAddToCart']);

        // Add composer libraries to the about section (v0.12+)
        $dispatcher->addListener(\Commerce::EVENT_DASHBOARD_LOAD_ABOUT, [$this, 'addLibrariesToAbout']);
    }

    public function onAddToCart(Item $event): void
    {
        $item = $event->getItem();
        $keys = $this->getAllowedKeys();

        foreach ($keys as $key) {
            $val = (int)$event->getOption($key);
            if (empty($val)) {
                continue;
            }

            /** @var comProduct $product */
            $product = $this->adapter->getObject(comProduct::class, ['id' => $val]);
            if (!$product) {
                $this->adapter->log(modX::LOG_LEVEL_WARN, "[ItemOptions] Received {$key}={$val}, however no product with ID {$val} found.");
                continue;
            }

            $name = $product->getName();
            $price = $product->getPricing($this->commerce->currency)->getPriceForItem($item)->getInteger();
            $quantity = max(1, (int)$event->getOption($key . '_quantity', 1));
            if ($quantity > 1) {
                $name = "{$quantity}x {$name}";
                $price *= $quantity;
            }

            /** @var comOrderItemExtraAdjustment $adjustment */
            $adjustment = $this->adapter->newObject(comOrderItemExtraAdjustment::class);
            $adjustment->fromArray([
                'key' => 'itemopt_' . $key,
                'name' => $name,
                'price_change' => $price,
                'price_change_per_quantity' => true,
                'show_on_order' => true,
            ]);

            $adjustment->setProperty('itemopt_product', [
                'product_id' => $product->get('id'),
                'sku' => $product->getSku()
            ]);
            $item->addPriceAdjustment($adjustment);
        }
    }

    public function getModuleConfiguration(\comModule $module): array
    {
        $fields = [];

        $fields[] = new TextField($this->commerce, [
            'label' => $this->adapter->lexicon('commerce_itemoptions.allowed_fields'),
            'description' => $this->adapter->lexicon('commerce_itemoptions.allowed_fields.desc'),
            'name' => 'properties[allowed_fields]',
            'value' => $module->getProperty('allowed_fields', ''),
        ]);

        return $fields;
    }

    public function addLibrariesToAbout(PageEvent $event)
    {
        $lockFile = dirname(__DIR__, 2) . '/composer.lock';
        if (file_exists($lockFile)) {
            $section = new SimpleSection($this->commerce);
            $section->addWidget(new ComposerPackages($this->commerce, [
                'lockFile' => $lockFile,
                'heading' => $this->adapter->lexicon('commerce.about.open_source_libraries') . ' - ' . $this->adapter->lexicon('commerce_itemoptions'),
                'introduction' => '', // Could add information about how libraries are used, if you'd like
            ]));

            $about = $event->getPage();
            $about->addSection($section);
        }
    }

    private function getAllowedKeys()
    {
        $fields = $this->getConfig('allowed_fields', '');
        $fields = array_map('trim', explode(',', $fields));
        return array_filter($fields);
    }
}
