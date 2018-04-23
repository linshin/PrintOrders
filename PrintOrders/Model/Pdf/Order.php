<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Stonewave\PrintOrders\Model\Pdf;

/**
 * Sales Order PDF model
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class Order extends \Magento\Sales\Model\Order\Pdf\AbstractPdf
{
    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $_storeManager;

    /**
     * @var \Magento\Framework\Locale\ResolverInterface
     */
    protected $_localeResolver;

    /**
     * @param \Magento\Payment\Helper\Data $paymentData
     * @param \Magento\Framework\Stdlib\StringUtils $string
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Magento\Framework\Filesystem $filesystem
     * @param Config $pdfConfig
     * @param \Magento\Sales\Model\Order\Pdf\Total\Factory $pdfTotalFactory
     * @param \Magento\Sales\Model\Order\Pdf\ItemsFactory $pdfItemsFactory
     * @param \Magento\Framework\Stdlib\DateTime\TimezoneInterface $localeDate
     * @param \Magento\Framework\Translate\Inline\StateInterface $inlineTranslation
     * @param \Magento\Sales\Model\Order\Address\Renderer $addressRenderer
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param \Magento\Framework\Locale\ResolverInterface $localeResolver
     * @param array $data
     *
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        \Magento\Payment\Helper\Data $paymentData,
        \Magento\Framework\Stdlib\StringUtils $string,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Framework\Filesystem $filesystem,
        \Magento\Sales\Model\Order\Pdf\Config $pdfConfig,
        \Magento\Sales\Model\Order\Pdf\Total\Factory $pdfTotalFactory,
        \Magento\Sales\Model\Order\Pdf\ItemsFactory $pdfItemsFactory,
        \Magento\Framework\Stdlib\DateTime\TimezoneInterface $localeDate,
        \Magento\Framework\Translate\Inline\StateInterface $inlineTranslation,
        \Magento\Sales\Model\Order\Address\Renderer $addressRenderer,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Framework\Locale\ResolverInterface $localeResolver,
        array $data = []
    ) {
        $this->_storeManager = $storeManager;
        $this->_localeResolver = $localeResolver;
        parent::__construct(
            $paymentData,
            $string,
            $scopeConfig,
            $filesystem,
            $pdfConfig,
            $pdfTotalFactory,
            $pdfItemsFactory,
            $localeDate,
            $inlineTranslation,
            $addressRenderer,
            $data
        );
    }

    /**
     * Return PDF document
     *
     * @param array|Collection $orders
     * @return \Zend_Pdf
     */
    public function getPdf($orders = [])
    {
        $this->_beforeGetPdf();

        $pdf = new \Zend_Pdf();
        $this->_setPdf($pdf);
        $style = new \Zend_Pdf_Style();
        $this->_setFontBold($style, 10);
        // $page = $this->newPage();

        foreach ($orders as $order) {
            $page = $this->newPage();
            if ($order->getStoreId()) {
                $this->_localeResolver->emulate($order->getStoreId());
                $this->_storeManager->setCurrentStore($order->getStoreId());
            }

            /* Add image */
            $this->insertLogo($page, $order->getStore());

            /* Add address */
            $this->insertAddress($page, $order->getStore());

            /* Add head */
            $this->insertOrder(
                $page,
                $order,
                false
            );

            /* Add document text and number */
            $this->insertDocumentNumber($page, __('Order # ') . $order->getIncrementId());
            
            /* Add table */
            $this->_drawHeader($page);

            /* Add body */
            foreach ($order->getAllVisibleItems() as $item) {
                /* Draw item */
                $this->drawItem($page, $order, $item);
                $page = end($pdf->pages);
            }

            /* Add totals */
            $this->insertOrderTotals($page, $order);

            if ($order->getStoreId()) {
                $this->_localeResolver->revert();
            }
        }
        $this->_afterGetPdf();
        return $pdf;
    }

    /**
     * Draw header for item table
     *
     * @param \Zend_Pdf_Page $page
     * @return void
     */
    protected function _drawHeader(\Zend_Pdf_Page $page)
    {
        /* Add table head */
        $this->_setFontRegular($page, 10);
        $page->setFillColor(new \Zend_Pdf_Color_Rgb(0.93, 0.92, 0.92));
        $page->setLineColor(new \Zend_Pdf_Color_GrayScale(0.5));
        $page->setLineWidth(0.5);
        $page->drawRectangle(25, $this->y, 570, $this->y - 15);
        $this->y -= 10;
        $page->setFillColor(new \Zend_Pdf_Color_Rgb(0, 0, 0));

        //columns headers
        $lines[0][] = ['text' => __('Products'), 'feed' => 35];
        $lines[0][] = ['text' => __('SKU'), 'feed' => 290, 'align' => 'right'];
        $lines[0][] = ['text' => __('Qty'), 'feed' => 435, 'align' => 'right'];
        $lines[0][] = ['text' => __('Price'), 'feed' => 360, 'align' => 'right'];
        $lines[0][] = ['text' => __('Tax'), 'feed' => 495, 'align' => 'right'];
        $lines[0][] = ['text' => __('Subtotal'), 'feed' => 565, 'align' => 'right'];

        $lineBlock = ['lines' => $lines, 'height' => 5];

        $this->drawLineBlocks($page, [$lineBlock], ['table_header' => true]);
        $page->setFillColor(new \Zend_Pdf_Color_GrayScale(0));
        $this->y -= 15;
    }

    protected function drawOrderId(\Zend_Pdf_Page $page, $order)
    {
        $this->_setFontRegular($page, 10);
        $page->setFillColor(new \Zend_Pdf_Color_Rgb(0.93, 0.92, 0.92));
        $page->setLineColor(new \Zend_Pdf_Color_GrayScale(0.5));
        $page->setLineWidth(0.5);
        $page->drawRectangle(25, $this->y, 570, $this->y - 15);
        $this->y -= 10;
        $page->setFillColor(new \Zend_Pdf_Color_Rgb(0, 0, 0));

        //columns headers
        $lines[0][] = ['text' => __('Order Id: '. $order->getIncrementId()), 'feed' => 35];

        $lineBlock = ['lines' => $lines, 'height' => 5];

        $this->drawLineBlocks($page, [$lineBlock], ['table_header' => true]);
        $page->setFillColor(new \Zend_Pdf_Color_GrayScale(0));
        //$this->y -= 10;
    }

    protected function drawItem(\Zend_Pdf_Page $page, $order, $item)
    {
        $array = [];
        if($item->getProductType() == 'configurable') {
            $options = $item->getProductOptions();
            if(isset($options['attributes_info'])) {
                foreach ($options['attributes_info'] as $attribute) {
                    $array[] = $attribute['value']; 
                }
            }
        }

        $this->_setFontRegular($page, 10);
        $page->setLineWidth(0.5);
        $this->y -= 10;

        //columns
        $lines[0][] = ['text' => $item->getName() . ' ' .implode('-', $array) , 'feed' => 35];
        $lines[0][] = ['text' => $item->getSku(), 'feed' => 290, 'align' => 'right'];
        $lines[0][] = ['text' => $item->getQtyOrdered(), 'feed' => 435, 'align' => 'right'];
        $lines[0][] = ['text' => $order->formatPriceTxt($item->getPrice()), 'feed' => 360, 'align' => 'right'];
        $lines[0][] = ['text' => $order->formatPriceTxt($item->getTaxAmount()), 'feed' => 495, 'align' => 'right'];
        $lines[0][] = ['text' => $order->formatPriceTxt($item->getPrice()*$item->getQtyOrdered()), 'feed' => 565, 'align' => 'right'];

        $lineBlock = ['lines' => $lines, 'height' => 5];

        $this->drawLineBlocks($page, [$lineBlock], ['table_header' => true]);
        $this->y -= 10;
    }

    /**
     * Insert totals to pdf page
     *
     * @param  \Zend_Pdf_Page $page
     * @param  \Magento\Sales\Model\AbstractModel $source
     * @return \Zend_Pdf_Page
     */
    protected function insertOrderTotals($page, $order)
    {
        $lineBlock = ['lines' => [], 'height' => 15];

        $lineBlock['lines'][] = [
            [
                'text' => 'Τελικό Σύνολο:',
                'feed' => 475,
                'align' => 'right',
                'font_size' => 16,
                'font' => 'bold',
            ],
            [
                'text' => $order->formatPriceTxt($order->getGrandTotal()),
                'feed' => 565,
                'align' => 'right',
                'font_size' => 16,
                'font' => 'bold'
            ],
        ];
                
        $this->y -= 20;
        $page = $this->drawLineBlocks($page, [$lineBlock]);
        return $page;
    }

    /**
     * Create new page and assign to PDF object
     *
     * @param  array $settings
     * @return \Zend_Pdf_Page
     */
    public function newPage(array $settings = [])
    {
        /* Add new table head */
        $page = $this->_getPdf()->newPage(\Zend_Pdf_Page::SIZE_A4);
        $this->_getPdf()->pages[] = $page;
        $this->y = 800;
        if (!empty($settings['table_header'])) {
            $this->_drawHeader($page);
        }
        return $page;
    }

}
