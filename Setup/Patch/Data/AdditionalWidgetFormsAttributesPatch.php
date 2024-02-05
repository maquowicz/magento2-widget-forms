<?php
/**
 * Copyright © Alekseon sp. z o.o.
 * http://www.alekseon.com/
 */
declare(strict_types=1);

namespace Alekseon\WidgetForms\Setup\Patch\Data;

use Alekseon\AlekseonEav\Model\Adminhtml\System\Config\Source\Scopes;
use Alekseon\CustomFormsBuilder\Model\FormFactory;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Framework\Setup\Patch\PatchRevertableInterface;

class AdditionalWidgetFormsAttributesPatch implements DataPatchInterface, PatchRevertableInterface
{
    /**
     * @var ModuleDataSetupInterface
     */
    private $moduleDataSetup;
    /**
     * @var \Alekseon\AlekseonEav\Setup\EavDataSetupFactory
     */
    private $eavSetupFactory;
    /**
     * @var \Alekseon\CustomFormsBuilder\Model\Form\AttributeRepository
     */
    private $formAttributeRepository;
    /**
     * @var FormFactory
     */
    private $formFactory;

    /**
     * @param ModuleDataSetupInterface $moduleDataSetup
     * @param \Alekseon\AlekseonEav\Setup\EavDataSetupFactory $eavSetupFactory
     * @param \Alekseon\CustomFormsBuilder\Model\Form\AttributeRepository $formAttributeRepository
     */
    public function __construct(
        ModuleDataSetupInterface $moduleDataSetup,
        \Alekseon\AlekseonEav\Setup\EavDataSetupFactory $eavSetupFactory,
        \Alekseon\CustomFormsBuilder\Model\Form\AttributeRepository $formAttributeRepository,
        FormFactory $formFactory
    ) {
        $this->moduleDataSetup = $moduleDataSetup;
        $this->eavSetupFactory = $eavSetupFactory;
        $this->formAttributeRepository = $formAttributeRepository;
        $this->formFactory = $formFactory;
    }

    /**
     * @inheritdoc
     */
    public function apply()
    {
        $this->moduleDataSetup->getConnection()->startSetup();

        $eavSetup = $this->eavSetupFactory->create();
        $eavSetup->setAttributeRepository($this->formAttributeRepository);


        $this->createAdditionalWidgetFormAttributes($eavSetup);
        $this->moduleDataSetup->getConnection()->endSetup();
        return $this;
    }

    /**
     * @param $eavSetup
     * @return void
     */
    private function createAdditionalWidgetFormAttributes($eavSetup)
    {
        $eavSetup->createOrUpdateAttribute(
            'allow_guest_submit',
            [
                'frontend_input' => 'boolean',
                'frontend_label' => 'Allow Guest Submit',
                'visible_in_grid' => false,
                'is_required' => false,
                'sort_order' => 1,
                'group_code' => 'widget_form_attribute',
                'scope' => Scopes::SCOPE_GLOBAL,
                'note' => 'Should guest form submit be allowed. Set to \'yes\' only in exceptional cases, such as some anonymous survey'
            ]
        );

        $eavSetup->createOrUpdateAttribute(
            'required_record_params',
            [
                'frontend_input' => 'multiselect',
                'frontend_label' => 'Required Record Params',
                'backend_type' => 'varchar',
                'source_model' => 'Alekseon\WidgetForms\Model\Attribute\Source\Parameters',
                'visible_in_grid' => false,
                'is_required' => false,
                'sort_order' => 2,
                'group_code' => 'widget_form_attribute',
                'scope' => Scopes::SCOPE_GLOBAL,
                'note' => ('Required params to be sent to form submit or load controller. Warning, params such as order_id or order_item_id ' .
                           'presume that customer must be logged in. Also')
            ]
        );
    }

    /**
     * @inheritdoc
     */
    public static function getDependencies()
    {
        return [];
    }

    /**
     * @inheritdoc
     */
    public function revert()
    {
        $this->moduleDataSetup->getConnection()->startSetup();

        $eavSetup = $this->eavSetupFactory->create();
        $eavSetup->setAttributeRepository($this->formAttributeRepository);

        $eavSetup->deleteAttribute('allow_guest_submit');
        $eavSetup->deleteAttribute('required_record_params');
        $this->moduleDataSetup->getConnection()->endSetup();
    }

    /**
     * @inheritdoc
     */
    public function getAliases()
    {
        return [];
    }
}
