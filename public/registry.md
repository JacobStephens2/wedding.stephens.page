# Overview
On the registry page should be registry functionality. We should be able to have a way to add links to add to the registry on the the registry page.

# Adding Registry Items
There should be a file in the private directory in which we can add links to registry items, perhaps a JSON file which we can modify to add items to the registry.

Alternatively there could be a private, password protected admin page which we could use to add registry items. This page could store registry items in the MySQL database upon entry.

Admins should be able to add a price to the items, and this should display on the registry page.

# Ability for Users to Mark Registry Items as Purchased
Site visitors should be able to mark registry items as purchased so that other visitors can know what has and has not been purchased.

# Editing Registry Items
There should be a way on the /admin-registry page to edit registry items.

# Style
The description of registry items on the registry page should be cased in sentence case. This will require using a different font for the description.

# Sorting the Registry List
There should be a select element which lets the user viewing the registry page sort the registry items by the following:
- Price low to high
- Price high to low

The available registry items should always be displayed first in the list.