<?php

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class GenerateVariationSeeders extends Command
{
    protected $signature = 'generate:variation-seeders';

    protected $description = 'Generate Seeder files based on real product variation data in the database.';

    public function handle()
    {
        $this->info('Reading product variations...');

        $attributes = [];  // attribute_name => [values]
        $productMap = [];  // product_id => [attribute names]

        // Eager load variations if it's a relationship, assuming it's a JSON field for now
        $products = Product::whereNotNull('variations')->get();
        foreach ($products as $product) {

            $json = json_decode($product->variations, true);

            if (! is_array($json)) {
                continue;
            }

            foreach ($json as $variation) {

                $attrName = trim($variation['name'] ?? '');
                $rawValue = trim($variation['value'] ?? '');

                if ($attrName === '' || $rawValue === '') {
                    continue;
                }

                // Handle multiple comma-separated values
                $values = array_map('trim', explode(',', $rawValue));

                // Register attribute
                if (! isset($attributes[$attrName])) {
                    $attributes[$attrName] = [];
                }

                // Register values
                foreach ($values as $v) {
                    if (! in_array($v, $attributes[$attrName])) {
                        $attributes[$attrName][] = $v;
                    }
                }

                // Register product mapping
                if (! isset($productMap[$product->id])) {
                    $productMap[$product->id] = [];
                }

                if (! in_array($attrName, $productMap[$product->id])) {
                    $productMap[$product->id][] = $attrName;
                }
            }
        }

        // Generate seeders
        $this->generateAttributeSeeder($attributes);
        $this->generateAttributeValueSeeder($attributes);
        $this->generateVariationSeeder($productMap);

        $this->info("\n✔ Seeders generated successfully!");

        return 0;
    }

    private function escapeStringForSeeder($string)
    {
        // Escape single quotes for use in single-quoted PHP strings in the seeder file
        return str_replace("'", "\\'", $string);
    }

    private function generateAttributeSeeder($attributes)
    {
        $content = "<?php\n\nnamespace Database\\Seeders;\n\n".
            "use Illuminate\\Database\\Seeder;\n".
            "use App\\Models\\ProductAttribute;\n\n".
            "class ProductAttributeSeeder extends Seeder\n".
            "{\n    public function run()\n    {\n".
            "        \$attributes = [\n";

        foreach (array_keys($attributes) as $name) {
            // FIX: Use escapeStringForSeeder and correct array generation (just the name is needed)
            $content .= "            '".$this->escapeStringForSeeder($name)."',\n";
        }

        $content .= "        ];\n\n".
            "        foreach (\$attributes as \$attr) {\n".
            "            ProductAttribute::firstOrCreate(['name' => \$attr]);\n". // Use firstOrCreate to prevent duplicates
            "        }\n".
            "    }\n".
            "}\n";

        File::put(database_path('seeders/ProductAttributeSeeder.php'), $content);
        $this->info('✔ ProductAttributeSeeder.php created');
    }

    private function generateAttributeValueSeeder($attributes)
    {
        $content = "<?php\n\nnamespace Database\\Seeders;\n\n".
            "use Illuminate\\Database\\Seeder;\n".
            "use App\\Models\\ProductAttribute;\n".
            "use App\\Models\\ProductAttributeValue;\n\n".
            "class ProductAttributeValueSeeder extends Seeder\n".
            "{\n    public function run()\n    {\n".
            "        \$data = [\n";

        foreach ($attributes as $name => $values) {
            // FIX: Use escapeStringForSeeder for the attribute name
            $content .= "            '".$this->escapeStringForSeeder($name)."' => [\n";
            foreach ($values as $v) {
                // FIX: Use escapeStringForSeeder for the attribute value
                $content .= "                '".$this->escapeStringForSeeder($v)."',\n";
            }
            $content .= "            ],\n";
        }

        $content .= "        ];\n\n".
            "        foreach (\$data as \$attrName => \$values) {\n".
            "            \$attribute = ProductAttribute::where('name', \$attrName)->first();\n".
            "            if (!\$attribute) continue;\n\n".
            "            foreach (\$values as \$value) {\n".
            "                ProductAttributeValue::firstOrCreate(\n". // Use firstOrCreate to prevent duplicates
            "                    ['product_attribute_id' => \$attribute->id, 'value' => \$value],\n".
            "                    ['product_attribute_id' => \$attribute->id, 'value' => \$value]\n".
            "                );\n".
            "            }\n".
            "        }\n".
            "    }\n".
            "}\n";

        File::put(database_path('seeders/ProductAttributeValueSeeder.php'), $content);
        $this->info('✔ ProductAttributeValueSeeder.php created');
    }

    private function generateVariationSeeder($productMap)
    {
        $content = "<?php\n\nnamespace Database\\Seeders;\n\n".
            "use Illuminate\\Database\\Seeder;\n".
            "use App\\Models\\Product;\n".
            "use App\\Models\\ProductVariation;\n".
            "use App\\Models\\ProductAttributeValue;\n".
            "use App\\Models\\ProductAttribute;\n\n". // Include ProductAttribute for name-based lookups
            "class ProductVariationSeeder extends Seeder\n".
            "{\n    public function run()\n    {\n".
            "        \$map = [\n";

        foreach ($productMap as $productId => $attrNames) {
            // Use escapeStringForSeeder for attribute names
            $names = array_map(fn ($n) => "'".str_replace("'", "\\'", $n)."'", $attrNames);
            $content .= "            $productId => [".implode(',', $names)."],\n";
        }

        $content .= "        ];\n\n".
            "        // Pre-fetch all attribute values mapped by attribute name for efficiency\n".
            "        \$allValues = ProductAttribute::with('attributeValues')->get()->keyBy('name')->map(fn(\$a) => \$a->attributeValues);\n\n".

            "        foreach (\$map as \$productId => \$attributes) {\n".
            "            \$product = Product::find(\$productId);\n".
            "            if (!\$product) continue;\n\n".
            "            // Create variations\n".
            "            for (\$i = 1; \$i <= 3; \$i++) {\n".
            "                \$variation = ProductVariation::firstOrCreate(\n". // Use firstOrCreate to prevent duplicates
            "                    ['product_id' => \$product->id, 'sku' => 'VAR-'.\$product->id.'-'.\$i],\n".
            "                    ['product_id' => \$product->id, 'sku' => 'VAR-'.\$product->id.'-'.\$i, 'price' => \$product->price, 'is_active' => true]\n".
            "                );\n\n".

            "                foreach (\$attributes as \$attrName) {\n".
            "                    \$values = \$allValues->get(\$attrName);\n". // Get values from the pre-fetched map
            "                    if (\$values && \$values->count()) {\n".
            "                        // Check if the pivot already exists before attaching\n".
            "                        \$randomValueId = \$values->random()->id;\n".
            "                        if (!\$variation->attributeValues()->where('product_attribute_value_id', \$randomValueId)->exists()) {\n".
            "                            \$variation->attributeValues()->attach(\$randomValueId);\n".
            "                        }\n".
            "                    }\n".
            "                }\n".
            "            }\n".
            "        }\n".
            "    }\n".
            "}\n";

        File::put(database_path('seeders/ProductVariationSeeder.php'), $content);
        $this->info('✔ ProductVariationSeeder.php created');
    }
}
