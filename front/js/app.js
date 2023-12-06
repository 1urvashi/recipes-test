'use strict'

document.addEventListener("DOMContentLoaded", function() {

            let recipesData;

            fetch("http://localhost:3001/recipes")
                .then(response => response.json())
                .then(data => {
                    recipesData = data;
                    Object.keys(data).forEach(recipeId => {
                        const recipe = data[recipeId];
                        displayRecipe(recipe);
                    });
                })
                .catch(error => console.error("Error fetching recipes:", error));

            // Function to display recipe details
            function displayRecipe(recipe) {
                const recipeContainer = document.getElementById("recipe-container");
                const recipeElement = document.createElement("div");

                recipeElement.innerHTML = `
                    <h2>${recipe.name}</h2>
                    <p>Servings: ${recipe.servings}</p>
                    <img src="${recipe.thumbnail}" alt="${recipe.name}">

                    <h3>Ingredients:</h3>
                    <ul>
                        ${recipe.ingredients.map(ingredient => `
                            <li>${ingredient.display}</li>
                        `).join('')}
                    </ul>

                    <h3>Instructions:</h3>
                    <ol>
                        ${Object.values(recipe.instructions).map(step => `
                            <li>${step.text}</li>
                        `).join('')}
                    </ol>
                    `;

                    recipeElement.classList.add("recipe");
                    recipeContainer.appendChild(recipeElement);
            }


            // Function to recalculate ingredients based on servings
            window.recalculateIngredients = function () {
            const servingsInput = document.getElementById("servings");
            const newServings = parseInt(servingsInput.value);

                if (!isNaN(newServings) && newServings > 0) {
                    Object.keys(recipesData).forEach(recipeId => {
                        const recipe = recipesData[recipeId];
                        const factor = newServings / parseInt(recipe.servings);

                        // Recalculate ingredient quantities
                        const recalculatedIngredients = recipe.ingredients.map(ingredient => {
                            const recalculatedQuantity = Math.ceil(ingredient.quantity * factor);
                            return { ...ingredient, quantity: recalculatedQuantity };
                        });
                    
                        recipe.servings = newServings;
                        recipe.ingredients = recalculatedIngredients;
                        
                        displayRecipe(recipe);
                    });
                } else {
                    alert("Please enter a valid number of servings.");
                }
            };

});