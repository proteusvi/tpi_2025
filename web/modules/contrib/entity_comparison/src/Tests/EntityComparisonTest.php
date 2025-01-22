<?php

namespace Drupal\entity_comparison\Tests;

use Drupal\entity_comparison\Entity\EntityComparison;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests the entity comparison functionality.
 *
 * @group Entity comparison
 */
class EntityComparisonTest extends BrowserTestBase {

  /**
   * Provides the default theme.
   *
   * @var string
   */
  protected $defaultTheme = 'stark';

  /**
   * Modules to enable.
   *
   * @var string[]
   */
  protected static $modules = ['entity_comparison', 'node'];

  /**
   * Don't check for or validate config schema.
   *
   * @var bool
   */
  protected $strictConfigSchema = FALSE;

  /**
   * User with privileges to do everything.
   *
   * @var Drupal\user\Entity\User|false
   */
  protected $adminUser;

  /**
   * Permissions for administrator user.
   *
   * @var array
   */
  public static $adminPermissions = [
    'access administration pages',
    'administer entity comparison',
    'bypass node access',
    'administer content types',
    'access content',
  ];

  /**
   * Test entity comparison.
   *
   * @var \Drupal\entity_comparison\Entity\EntityComparisonInterface
   */
  protected $entityComparison;

  /**
   * Store product contents.
   *
   * @var array
   */
  protected $products = [];

  /**
   * {@inheritdoc}
   */
  protected function setUp() : void {
    parent::setUp();

    // Collect admin permissions.
    $class = get_class($this);
    $adminPermissions = [];
    while ($class) {
      if (property_exists($class, 'adminPermissions')) {
        $adminPermissions = array_merge($adminPermissions, $class::$adminPermissions);
      }
      $class = get_parent_class($class);
    }

    // Create a store administrator user account.
    $this->adminUser = $this->drupalCreateUser($adminPermissions);

    // Admin login.
    $this->drupalLogin($this->adminUser);

    // Create product content type.
    $this->drupalCreateContentType(['type' => 'product', 'name' => 'Product']);

    // Add fields to product content type.
    $fields = [
      'price' => [
        'type' => 'string',
        'widget_type' => 'string_textfield',
      ],
      'sku' => [
        'type' => 'string',
        'widget_type' => 'string_textfield',
      ],
    ];
    $this->addFieldsToEntity('node', 'product', $fields);

    // Create product contents.
    $this->createProducts();

    // Create a test entity comparison for product.
    $this->entityComparison = EntityComparison::create([
      'label' => 'Product comparison',
      'id' => 'product_comparison',
      'add_link_text' => 'Add product to comparison list',
      'remove_link_text' => 'Remove product from the comparison',
      'limit' => 2,
      'entity_type' => 'node',
      'bundle_type' => 'product',
    ])->save();

    // Logout.
    $this->drupalLogout();
  }

  /**
   * Add fields to product content type.
   */
  protected function addFieldsToEntity($entity_type, $bundle_type, $fields) {
    foreach ($fields as $field_name => $field) {

      // Create a field.
      $field_storage = FieldStorageConfig::create([
        'field_name' => $field_name,
        'entity_type' => $entity_type,
        'type' => $field['type'],
      ]);

      $field_storage->save();
      FieldConfig::create([
        'field_storage' => $field_storage,
        'bundle' => $bundle_type,
        'label' => $field_name,
      ])->save();

      $entity_type_id = $entity_type . '.' . $bundle_type . '.' . 'default';
      \Drupal::entityTypeManager()->getStorage('entity_form_display')
        ->load($entity_type_id)
        ->setComponent($field_name, [
          'type' => $field['widget_type'],
          'settings' => [
            'placeholder' => 'A placeholder on ' . $field['type'],
          ],
        ])
        ->save();
      \Drupal::service('entity_display.repository')->getViewDisplay($entity_type, $bundle_type)
        ->setComponent($field_name)
        ->save();
    }
  }

  /**
   * Create text product contents.
   */
  protected function createProducts() {
    $products = [
      0 => [
        'type' => 'product',
        'title' => 'Product 1',
        'sku' => 'sku-1',
        'price' => '100$',
      ],
      1 => [
        'type' => 'product',
        'title' => 'Product 2',
        'sku' => 'sku-2',
        'price' => '120$',
      ],
      2 => [
        'type' => 'product',
        'title' => 'Product 3',
        'sku' => 'sku-3',
        'price' => '150$',
      ],
    ];

    foreach ($products as $product) {
      $this->products[] = $this->drupalCreateNode($product);
    }
  }

  /**
   * Use Field UI module to re-order fields.
   */
  protected function useFieldUi() {
    // Login as admin.
    $this->drupalLogin($this->adminUser);

    // Enable Field UI module.
    \Drupal::service('module_installer')->install(['field_ui']);

    // Grant Field UI permissions.
    $this->grantPermissions($this->adminUser->roles[0]->entity, [
      'administer node fields',
      'administer node form display',
      'administer node display',
      'administer display modes',
    ]);

    // Change custom view mode.
    $manage_display = '/admin/structure/types/manage/product/display/product_product_comparison';
    $edit = [
      'fields[price][region]' => 'content',
      'fields[sku][region]' => 'content',
      'fields[link_for_entity_comparison_product_comparison][region]' => 'hidden',
    ];
    $this->drupalGet($manage_display);
    $this->submitForm($edit, t('Save'));

    // Logout.
    $this->drupalLogout();
  }

  /**
   * Check that the admin user can see the entity comparison list.
   */
  public function testEntityComparisonListPage() {
    $this->drupalLogin($this->adminUser);
    $this->drupalGet('/admin/structure/entity_comparison');
    $this->assertSession()->pageTextContains('Product comparison');
  }

  /**
   * Check that the corresponding view mode is created and enabled successfully.
   */
  public function testViewmodeIsEnabled() {
    // Use Field UI module.
    $this->useFieldUi();

    $this->drupalLogin($this->adminUser);
    $this->drupalGet('/admin/structure/types/manage/product/display/product_product_comparison');
    $this->assertSession()->responseContains('product_comparison');
    $this->assertSession()->pageTextContains('Link for entity comparison');
  }

  /**
   * Test the dynamic permission.
   */
  public function testPermission() {
    // Allowed user.
    $allowed_user = $this->drupalCreateUser([
      'use product_comparison entity comparison',
      'access content',
    ]);
    $this->drupalLogin($allowed_user);
    $this->drupalGet('node/' . $this->products[0]->id());
    $this->assertSession()->pageTextContains('Add product to comparison list');
    $this->drupalLogout();

    // Denied user.
    $denied_user = $this->drupalCreateUser(['access content']);
    $this->drupalLogin($denied_user);
    $this->drupalGet('node/' . $this->products[0]->id());
    $this->assertSession()->pageTextNotContains('Add product to comparison list');
    $this->drupalLogout();
  }

  /**
   * Test the limit function.
   */
  public function testLimit() {
    // Log in.
    $allowed_user = $this->drupalCreateUser([
      'use product_comparison entity comparison',
      'access content',
    ]);
    $this->drupalLogin($allowed_user);

    // Add first product.
    $this->drupalGet('node/' . $this->products[0]->id());
    $this->clickLink('Add product to comparison list');
    $this->assertSession()->pageTextContains('You have successfully added Product 1 to Product comparison list.');

    $this->clickLink('Remove product from the comparison');
    $this->assertSession()->pageTextContains('You have successfully removed Product 1 from Product comparison.');
    $this->clickLink('Add product to comparison list');

    // Add second product.
    $this->drupalGet('node/' . $this->products[1]->id());
    $this->clickLink('Add product to comparison list');

    // Add first product.
    $this->drupalGet('node/' . $this->products[2]->id());
    $this->clickLink('Add product to comparison list');
    $this->assertSession()->pageTextContains('You can only add 2 items to the Product comparison list.');
  }

  /**
   * Test compare page function.
   */
  public function testComparePage() {
    // Log in.
    $allowed_user = $this->drupalCreateUser([
      'use product_comparison entity comparison',
      'access content',
    ]);
    $this->drupalLogin($allowed_user);

    // Add two product to the compare page.
    $this->drupalGet('node/' . $this->products[0]->id());
    $this->clickLink('Add product to comparison list');
    $this->drupalGet('node/' . $this->products[1]->id());
    $this->clickLink('Add product to comparison list');

    // Got to the compare page.
    $this->drupalGet('compare/product-comparison');
    $this->assertSession()->pageTextContains('Product 1');
    $this->assertSession()->pageTextContains('Product 2');

    $this->assertSession()->pageTextContains('Remove product from the comparison');

    $this->clickLink('Remove product from the comparison', 0);

    $this->assertSession()->pageTextContains('You have successfully removed Product 1 from Product comparison.');
  }

}
