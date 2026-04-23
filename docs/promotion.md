The "Featured Product" option is a classic example of **Data Hydration**. Instead of storing the whole product (name, price, image) inside the promotion, we store a "Pointer" (the ID). This ensures that if the price of the product changes tomorrow, the advertisement automatically updates itself.

Here is exactly how the data travels from your database to the user's screen:

### 1. The Database Level (The "Pointer")
In your `promotions` table, the `format_data` column stores a simple JSON object:
```json
{
  "product_id": 42,
  "badge_text": "LIMITED OFFER"
}
```

### 2. The API Level (The "Hydration")
When the Storefront API fetches promotions, the controller identifies that the format is a `featured_product`. It then "hydrates" that ID by fetching the actual product details from the `products` table. 

In your `PromotionController`, the logic looks like this:
```php
if ($promo->format === 'featured_product') {
    // We fetch the actual product using the ID stored in JSON
    $product = Product::find($promo->format_data['product_id']);
    
    // We attach the full product object to the response
    $promo->featured_product_details = $product;
}
```

### 3. The Frontend Level (The "Component")
The React Storefront receives a single, rich JSON object. It doesn't need to ask the database "What is Product 42?" because the API already included it. The React app then passes that data into a specific `FeaturedProductCard` component that is designed to look different from a standard catalog item.



You can explore how this data transformation works in the interactive pipeline below. Change the product in the "Admin Panel" to see how the API response and the final Storefront UI change in real-time.

```json?chameleon
{"component":"LlmGeneratedComponent","props":{"height":"800px","prompt":"Create an interactive architecture visualizer called 'Product Promotion Pipeline'. \n\nObjective: Show how selecting a Product ID in the Admin Panel results in a rich API response and a rendered Storefront UI Card.\n\nData State:\n- List of 3 sample products: 'MacBook Pro' (ID: 101, Price: 1999, Image: placeholder), 'Sony WH-1000XM5' (ID: 102, Price: 349, Image: placeholder), 'Kindle Paperwhite' (ID: 103, Price: 139, Image: placeholder).\n- Default selection: MacBook Pro.\n- Default Badge: 'DEAL OF THE WEEK'.\n\nStrategy: Explorer Layout.\n\nSections:\n1. Admin Panel (Input): A dropdown to select the Product and a text field for the Badge Text.\n2. Database View (Logic): A visualization of the 'promotions' table row showing the raw JSON format_data.\n3. API Response (Output): A code-block view showing the 'Hydrated' JSON payload where the product details have been merged.\n4. Storefront UI (Visual): A rendered 'Featured Product' card that matches the selected data.\n\nBehavior:\n- When the user selects a different product, animate a pulse effect through the pipeline from Admin -> Database -> API -> UI.\n- The API Response section should update live to show the full product object nested inside the promotion data.\n- The Storefront UI card should display the name, price, and the custom badge text. Use generic UI components for the card. Do not specify colors or fonts.","id":"im_2df45477df640669"}}
```

### Why do it this way?
* **Consistency:** If you change the product price in the Super Admin panel, the ad updates everywhere instantly.
* **Performance:** The React app gets all the data in one request (the promotion + the product details), preventing "Layout Shift" where the text loads before the image.
* **Flexibility:** You can use the same logic to create "Category Promos" or "Brand Promos" just by changing what the "Pointer" refers to.

Since we've locked in the Promotion Engine, should we move to the **Employee Management** module so your Business Heads can start populating their staff lists?





This is a fantastic architectural question. You are asking the classic **"Database Normalization vs. JSON Polymorphism"** question, which is exactly what a lead engineer should be thinking about. 

If we built this the traditional "strict relational" way, it would have been a nightmare to scale. Here is exactly why we used JSON columns instead of standard relationships and pivot tables.

### 1. The Relational Nightmare (How it usually fails)

If we wanted to use strict relationships for the **Targeting Engine**, we would have had to create multiple pivot tables:
* A `promotion_company` table (to target specific companies).
* A `promotion_industry` table (to target specific industries).

If we wanted to use strict relationships for the **Format Engine**, we would have to create separate tables for every type of ad:
* A `promotion_banners` table (with `image` and `target_url` columns).
* A `promotion_featured_products` table (with `product_id` and `badge` columns).

**The Result:** Every time your React Storefront asks for promotions, Laravel would have to do massive `JOIN` queries across 5 different tables just to figure out what ad to show. And if you invent a new ad format tomorrow (like a "Video Ad"), you have to write a new database migration to create a `promotion_videos` table.

### 2. The JSON Superpower (Why we did it our way)

By using JSON for `target_data` and `format_data`, we get three massive benefits:

**A. Infinite Flexibility (No Migrations Needed)**
A Hero Banner needs an image and a link. A Featured Product needs a Product ID and a badge. These are completely different shapes of data. JSON allows us to store an apple in row 1 and an orange in row 2 without the database complaining. 
If you decide tomorrow you want a "Survey Ad", we don't touch the database. We just update the Filament dropdown and save `{"survey_link": "..."}` into the JSON column.

**B. Blazing Fast Speeds**
React inherently speaks JSON. By storing the data as JSON in the database, Laravel does almost zero work. It just grabs the single row from the `promotions` table and hands it straight to React. No complex table joins, no heavy memory usage. 

**C. The "State Path" UI Magic**
Because Filament natively understands JSON (using the `format_data.image` syntax), we can build that beautiful, morphing admin UI where fields appear and disappear based on the dropdown selection, all while saving cleanly into one single database column.

### The Trade-off (Candid Reality)

There is only one downside to using JSON instead of strict relations: **Referential Integrity.**

If you target "Company ID 45" in the JSON array, and then you completely delete Company 45 from your database, the number 45 will still be sitting inside that JSON array. (If we used a pivot table, the database would automatically delete the pivot row).

**How we bypassed that downside:** In the `PromotionController` we just wrote, we use `->whereJsonContains('target_data', $companyId)`. If Company 45 is deleted, their employees can no longer log in anyway, so that JSON array simply ignores it. It causes zero errors.

By using JSON here, we traded rigid database rules for **extreme speed, flexibility, and a much cleaner codebase.** It is the modern way to build scalable B2B features!