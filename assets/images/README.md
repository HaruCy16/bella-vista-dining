# Bella Vista image assets

The Stage 1 templates reference the generated restaurant imagery through Manus-managed storage URLs so the live Preview can use the generated assets immediately. For an offline ZIP workflow, download the final generated images and replace the URLs in `includes/data.php`, `includes/header.php`, `includes/footer.php`, and the page templates with local paths under this folder.

Planned placements are:

- `hero-dining-room.jpg` — landing hero and checkout atmosphere
- `signature-crispy-pata.jpg` — menu and signature dish cards
- `garden-table.jpg` — reservation and gallery surfaces
- `chef-plating.jpg` — kitchen, menu, and gallery surfaces

The repository remains functional online without those local copies because the current references are absolute `/manus-storage/...` paths.
