<?php
// recipe_fetcher.php - Script to fetch daily recipes and save them to JSON

// Get API key from environment variable (set by GitHub Actions)
$apiKey = getenv('SPOONACULAR_API_KEY');

$outputFile = "daily_recipe.json";
$logFile = "fetch_log.txt";

// Log function
function logMessage($message) {
    global $logFile;
    $timestamp = date("Y-m-d H:i:s");
    file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND);
    echo "[$timestamp] $message\n"; // Also output to console for GitHub Actions log
}

logMessage("Starting recipe fetch process");

// Calculate which recipe to use based on day of year
$dayOfYear = date("z") + 1; // 1-366
$offset = $dayOfYear % 100;

// Add some randomness to avoid getting same recipes each year
$year = date("Y");
$seed = $dayOfYear + intval($year) % 10;
$tags = ["main course", "dinner", "lunch"];
$tag = $tags[$seed % count($tags)];

$url = "https://api.spoonacular.com/recipes/random?apiKey=$apiKey&number=1&tags=$tag";

logMessage("Fetching recipe with day of year: $dayOfYear");

try {
    $response = file_get_contents($url);
    
    if ($response === false) {
        throw new Exception("Failed to get API response");
    }
    
    $recipeData = json_decode($response, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("Invalid JSON response: " . json_last_error_msg());
    }
    
    if (!isset($recipeData['recipes']) || empty($recipeData['recipes'])) {
        throw new Exception("No recipes found in response");
    }
    
    $recipe = $recipeData['recipes'][0];
    
    // Format the recipe for your app
    $processedRecipe = [
        "version" => "1.0",
        "lastUpdated" => date("Y-m-d"),
        "recipe" => [
            "title" => $recipe['title'],
            "readyInMinutes" => $recipe['readyInMinutes'],
            "servings" => $recipe['servings'],
            "image" => $recipe['image'],
            "sourceUrl" => $recipe['sourceUrl'],
            "instructions" => $recipe['instructions'] ?? "See full recipe for instructions.",
            "summary" => $recipe['summary'] ?? "",
            "cuisines" => $recipe['cuisines'] ?? [],
            "dishTypes" => $recipe['dishTypes'] ?? [],
            "ingredients" => array_map(function($ingredient) {
                return $ingredient['original'];
            }, $recipe['extendedIngredients'])
        ]
    ];
    
    // Save to JSON file
    $success = file_put_contents($outputFile, json_encode($processedRecipe, JSON_PRETTY_PRINT));
    
    if ($success === false) {
        throw new Exception("Failed to write to output file");
    }
    
    logMessage("Successfully fetched and saved recipe: " . $recipe['title']);
    
} catch (Exception $e) {
    logMessage("ERROR: " . $e->getMessage());
    
    // Create a fallback recipe if fetch fails
    if (!file_exists($outputFile)) {
        createFallbackRecipe($outputFile);
        logMessage("Created fallback recipe due to API failure");
    }
}

logMessage("Recipe fetch process completed");

// Fallback recipe function
function createFallbackRecipe($outputFile) {
    $fallbackRecipe = [
        "version" => "1.0",
        "lastUpdated" => date("Y-m-d"),
        "recipe" => [
            "title" => "Classic Pasta Carbonara",
            "readyInMinutes" => 25,
            "servings" => 4,
            "image" => "https://spoonacular.com/recipeImages/716429-556x370.jpg",
            "sourceUrl" => "https://www.bbcgoodfood.com/recipes/ultimate-spaghetti-carbonara-recipe",
            "instructions" => "Boil pasta until al dente. Meanwhile, fry pancetta until crisp. Beat eggs with cheese and pepper. Drain pasta, quickly toss with egg mixture and pancetta. Serve immediately.",
            "summary" => "A traditional Italian pasta dish made with egg, hard cheese, pancetta and black pepper.",
            "cuisines" => ["Italian"],
            "dishTypes" => ["main course", "dinner"],
            "ingredients" => [
                "350g spaghetti",
                "150g pancetta or bacon, diced",
                "4 large eggs",
                "50g Pecorino Romano, grated",
                "50g Parmesan, grated",
                "Freshly ground black pepper",
                "1 garlic clove, minced (optional)",
                "Salt, to taste"
            ]
        ]
    ];
    
    file_put_contents($outputFile, json_encode($fallbackRecipe, JSON_PRETTY_PRINT));
}
?>
