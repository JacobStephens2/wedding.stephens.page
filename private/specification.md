# Overview
This is the specification for the wedding website of Jacob Stephens and Melissa Longua.

# URL of home page: https://wedding.stephens.page
The home page should be accessible at https://wedding.stephens.page.

URLs should not end with .php suffixes, for example:

Wrong: https://wedding.stephens.page/registry.php
Correct: https://wedding.stephens.page/registry

# Conventions
Some assets have JM in the name. This indicates that the asset depicts Jacob and Melissa.

# Favicon
The favicon.ico should have a matcha colored background, be a circle, and have a white "JM" in the middle.

# Directory organization
There is a public folder, which files to be served publicly by HTTP should be in, and there is a private folder, which should not be accessible by HTTP.

# Navigation
For desktop view, there should be links to different pages beneath the header of each page, as https://www.theknot.com/us/jack-dill-and-hannah-gifford-dec-2025/photos has. 

For mobile view, as this sample wedding site has, there should be a top right burger menu which can be clicked to open the menu.

Clicking "Jacob & Melissa" in the menu should return the user to the home page.

# Public Pages

## Home
This page should have a background video or image behind the entire page with a dark overlay to make the text more ledgible, and a central header saying:

Jacob & Melissa
April 11, 2026 | Philadelphia
[Days] to go!

The [Days] should be the number of days from now until April 11, 2026.


## RSVP
There should be a page which lets visitors to the website RSVP. If a visitor RSVPs, an email notification of the reservation should be sent to melissa.longua@gmail.com.

The top of this page should note the locations of the wedding:

St. Agatha St. James Parish
3728 Chestnut St, Philadelphia, PA 19104

Bala Golf Club
2200 Belmont Ave, Philadelphia, PA 19131

By the message field, ask if the filler has a request for a song which would get them on the dance floor.

### Invite Lookup
Like the way The Knot does it, such as at https://www.theknot.com/us/madison-scott-and-hunter-fellows-2026-06-06-9633b0cd-0fc4-4916-aa1d-f3a46eb648db/rsvp, the user should have to look up their name in the RSVP form to complete the RSVP for themselves.

If the user does not find themself, the form can say "Oops! We’re having trouble finding your invite. Please try another spelling of your name or contact the couple"

#### Invite Groups
Groups of guests should be put together by the Mailing Group value, so if three guests have a Mailing Group value of 71, then they should all be part of the same invite group, so they would all appear together as the user is filling out the RSVP form.

### Storing RSVPs
In addition to emailing notice of RSVP's, RSVP's should be stored in a MySQL database.

## Story
This page should have several sections for the user to scroll down through, telling the story of Jacob and Melissa's relationship chronologically.

Visitors should be able to click the photos on this page to view them larger.

This page should present the story of private/sources/The Story of How We Met - Wedding Website v2.md

### Carousels
Carousels on the story page should not automatically scroll. They should only go to the next photo after being clicked.

The carousels with only landscape photos (proposal and blessing) should not have black bars on the top and bottom of the photos.

Clicking on a photo in the carousel should show the photo that is being displayed.

### Meeting
Jacob and I met fusion dancing in Old City, Philadelphia, and kept meeting at various dance events in the city in October-November of 2024.

Carousel of these two photos. The first photo is portrait, make sure it entirely displays in the carousel, even if that means showing black bars on either side of it.
[2024-11-15_Fusion_dance_at_Concierge_Ballroom.jpg]
[2024-11-17_Rittenhop_Dip_Landscape.jpg]

### Dating
Jacob had been looking into becoming Catholic, and I had been to St. Agatha-St. James Church a couple of times since recently moving to Philly. We started attending events at St. AJs and dances throughout the city, and after a few dates and a spontaneous knock on my door with a TON of halal food and hummus, we started officially dating.

These two photos should be a carousel, as the proposal and blessing section carousels:
[2025-01-16_Mel_and_Jacob_2_dip_bw.jpg]
[2025-03-04_Mardi_Gras.JPG]

### Proposal
Jacob proposed to Melissa on Mount Jimmy Stepson in the Canadian Rockies, overlooking Peyto Lake, while a guitarist played Can't Help Falling In Love in the background.

There should be a carousel of these two photos. The carousel should be full width to the containing element. The carou
[PeytoLakeBanff_Proposal_One_Knee_wide.jpg]
[PeytoLakeBanff_Proposal_Closeup_Smile.jpg]

Below the carousel should be this YouTube video of the proposal should be embedded in the proposal section of the story page: https://youtu.be/iEbqiWzH800

### Blessing
The day after the proposal, Fr. Remi Morales of St. Agatha St. James church blessed Jacob and Melissa's engagement, surrounded by their parents and many friends.

This YouTube video of the blessing should be embedded in the blessing section of the story page: https://youtu.be/dko2cded45ES

Carousel of these photos:
[Landscape_JM_at_Altar.jpg]
[JM_With_Parents_at_Scannichios.jpg]

### Wedding
The wedding will be on April 11, 2026 at St. Agatha St. James Parish in Philadelphia. The reception will be hosted at Bala Golf Club.

The wedding section of the story page should include these two photos, laid out with block styling rather than inline styling, so they appear top to bottom of one another.
[Church_Interior_Mass_Kneeling_Ordination.jpg]
[Bala-Golf-Club-outdoor-view.jpg]

It's been quite the journey of faith and hope. God has been present every step of the way. We still love dancing and being very involved in our parish community, and are excited to be preparing for our sacramental wedding. Jacob entered the Catholic Church in fullness on Divine Mercy Sunday, 2025, and our wedding date is set for the eve of Divine Mercy Sunday, 2026. God has made us new and continues to make us new and give us new life and new hearts, and we see His beauty and His hand in our Easter Octave wedding date.


## Registry
There should be a page that displays Jacob and Melissa's registry.

There should be a prompt on the registry page requesting the user to mark if something was purchased. I can imagine people forgetting to do this. The prompt should be small and stick to the top of the user's display after they scroll past it.

After the user clicks a view item link and then navigates away from the page, after they then return back to this registry tab a pop up should display prompting them to mark the item as purchased if they purchased it. The user should not have to reload the page to see this prompt, so the prompt can display as soon as the user clicks a View Item link, that way it will be visible when they return to the tab if they haven't closed the page.

The font for descriptions on registry items should maintain the casing the user entered into the description as they created the registry item.

### House Fund
The image for the house fund is public/images/house-fund.jpg

There should be an arrow the user can click on the house fund section to fold up and hide most of the house fund section, leaving only the title of the section and the clickable unfold icon. The chevron should be on the top right of the house fund container.

The default state of the house fund section should be open on page load.

## About
There should be an "about" page linked to from the menu. This page should have info about how kids are allowed and about the nuptial Mass and Communion. We can borrow wording from Kiera and Brett's wedding website if they have any relevant wording: https://www.zola.com/wedding/brettandkiera

## Travel
This page should have information about parking and transportation as well as information about our hotel block.

### Accommodations
We have a room block at the Residence Inn by Marriott (615 Righters Ferry Rd, Bala Cynwyd, PA 19004). The group rate booking link is: https://app.marriott.com/reslink?id=1769545389844&key=GRP&app=resvlink — available through March 11, 2026. A note on the page advises guests that they may find rooms closer to Bala Golf Club at a lower rate by searching independently. Rooms in this block are $300 per night.

We have another room block at Courtyard by Marriott Philadelphia City Avenue
- 4100 Presidential Boulevard Philadelphia, Pennsylvania, USA, 19131
for $200 per night. (booking link: https://app.marriott.com/reslink?id=1770919186656&key=GRP&app=resvlink)

Here is information from Saint AJ's website about parking near the church: "Free parking is available for all Sunday Masses in the Santander Bank parking lot across the street from the Church."

For St. AJ's, Street parking nearby is metered and generally available.

Further, there is a parking garage on the block at https://facilities.upenn.edu/maps/locations/parking-garage-119-s-38th-street for about $15-19 for all day parking.

### Coordinating Out-of-Town Guests
We're happy to help coordinate travel arrangements for out-of-town guests. If you're traveling from out of town and need assistance with travel planning, group accommodations, or have questions about getting to Philadelphia, please reach out to us through the contact form or email melissa.longua@gmail.com. We can help coordinate shared transportation, group hotel bookings, and provide recommendations for the best ways to get to the wedding venues.

## Contact
There should be a form that lets users send emails to Jacob and Melissa at melissa.longua@gmail.com. Mandrill SMTP can be used to send these emails, with credentials in the private .env file.

Above the contact form, Jacob's mailing address should be displayed for guests who want to send cards or letters:

Jacob Stephens
3815 Haverford Ave, Unit 1
Philadelphia, PA 19104

Jacob's cell phone number should be on the contact page: 484 356 7773

# Admin Pages

## Check RSVPs
There should be a password protected page accessible at https://wedding.stephens.page/check-rsvps, which is not listed in the site menus. The password is the RSVP_CHECK_PASSWORD value in the .env file. This page should show the user the RSVPs stored in the database.

There should be a link on the logged in and logged out view of this page which takes the user back to the main site.

## Invite Lookup Management Page in Admin Area
There should be a page in the admin area which lets admins manage the invite list. The initial set of data should come from the private/Guest List Feb 10 2026.csv file. This CRUD area should show first name, last name, group number, and RSVP status.

The header columns of the guest management page (/admin-guests) should stay sticky at the top of the page on scroll so the admin can continue to see what each column is as they scroll down.

### Giving a guest a plus one
Admins should be able to give guests a plus one. If a guest has a plus one, then when they look themself up in the invite lookup of the RSVP page, they should see their name as well as a blank name field for their plus one. They should be able to indicate whether or not they are bringing a plus one.

## Manage RSVPs

## Manage House Fund
In the admin area there should be a manage house fund page which allows the user to manage the house fund (CRUD operations on entries).

## Manage Registry
The font used in the description field for registry item management should display the casing the user entered, like the description display on the registry display page. The font here should display both upper and lower case letters, not just upper case letters that are taller or shorter.

On a wide enough viewport, the list of registry items below the entry form should display in a grid like the list on the public registry page.

The font for the descriptions in this view in the list should be user case as well, like on the public registry view page.

### Registry Item Ordering
There should be a way that we can manually order the default order the registry items appear in on the public registry page.

### Publishing Status for Registry Items
There should be a way to set the publishing status of registry items. By default new items should have a published status of "Published", meaning they display on the public registry page, but in editing registry items, admins should be able to set the registry item status to "Unpublished", which makes the registry item only visible in the registry management area, but not in the public list.

# Redirects

## Blessing
Navigating to https://wedding.stephens.page/blessing should direct visitors to a page on the wedding website which has the content described in the blessing section of the story page.

The blessing page should not have a link to it in the navigation of the site, rather, it should only be acessible by direct URL access.


# Technology
This website has the following technologies available to it:
- Ubuntu 24.04.3
- Apache 2.4.58
- PHP 8.3.6
    - composer for package management
    - phpdotenv for environment variables
- MySQL 8.0.43 (credentials in .env file)
- JavaScript (Primarily run in Google Chrome)
- CSS
- HTML
- Playwright MCP Server for browsing
- MailChimp SMTP (Mandrill)
- certbot for SSL certificates

When updates are made to css and js files, references to them should be updated so as to bust clients' caches of these files.

# Style

## Formatting dependent on point of view
If the paragraph is written in the first person, it should be italicized.

## Colors
Our wedding colors are green and gold. Further, we like these naturally inspired colors for our published materials:
- Cloudy Sky Lavendar: #9597a3
- Thin Matcha: #7f8f65
- Blush: hsl(13 37% 68% / 1)

## Elements
We like floral and leafy accents.

## Fonts
We like seriffed, formal, modern fonts - not too flowy, but more strong, such as:
- Cinzel Regular
- Beloved Script Regular

Text should be left justified, not fully justified, so as to prevent large rivers from text sections viewed on mobile.

## Text
The max width of the text on the page should be 80 characters.

# Footer
The footer should note that the website was created by Jacob Stephens, and possibly link to his portfolio page: https://stephens.page/

# Analytics by Google Tag
Here is the Google Tag for this site's Analytics:

<!-- Google tag (gtag.js) -->
<script async src="https://www.googletagmanager.com/gtag/js?id=G-DQN0TVHB1Z"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('js', new Date());

  gtag('config', 'G-DQN0TVHB1Z');
</script>

# Handling Expired Sessions
There should be a way to preserve data entered into the registry new item form such that when enter clicked to save, even if the user's session has expired, the data they entered is preserved through their login.