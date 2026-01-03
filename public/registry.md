# Overview
On the registry page should be registry functionality. We should be able to have a way to add links to add to the registry on the the registry page.

# Registry Admin Area

## Adding Registry Items
There should be a file in the private directory in which we can add links to registry items, perhaps a JSON file which we can modify to add items to the registry.

Alternatively there could be a private, password protected admin page which we could use to add registry items. This page could store registry items in the MySQL database upon entry.

Admins should be able to add a price to the items, and this should display on the registry page.

## Editing Registry Items
There should be a way on the /admin-registry page to edit registry items.

# Registry List

## Ability for Users to Mark Registry Items as Purchased
Site visitors should be able to mark registry items as purchased so that other visitors can know what has and has not been purchased.

## Style
The description of registry items on the registry page should be cased in sentence case. This will require using a different font for the description.

## Sorting the Registry List
There should be a select element which lets the user viewing the registry page sort the registry items by the following:
- - Select -
- Price low to high
- Price high to low

The available registry items should always be displayed first in the list.

## Images for Registry List Items
The full images for the items should be displayed, not cut off. The image display should be able to display full portrait or landscape shaped images, while maintaining a consistent overall registry item container size. This means some images will have to be shrunk to fully display in the image box.

If a user clicks the image of a registry item, the image should appear full screen in a pop up modal that they can close by clicking out of it, clicking an x in the corner of it, or clicking escape on their keyboard.

## House Fund
Above the registry list should be a house fund section. This should notify the user that they can contribute to our house fund as a wedding gift. The section should list a few payment methods: 
- Venmo to Melissa @Melissa-Longua
- Check addressed to Jacob Stephens, if mailing, send to 3815 Haverford Ave, Unit 1, Philadelphia, PA 19104

This section should let the user enter how much they contributed, so visitors to the page can see how much total has so far been contributed to the house fund.

On desktop view, the house fund section should have its payment methods container side by side with the total contributed box when the viewport is wide enough to limit the vertical scrolling needed to get to the gifts below.