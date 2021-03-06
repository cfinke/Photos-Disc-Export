Photos Disc Export
==================

Export your Mac OS Photos library as a browsable website that can be burned to a disc and remain completely functional offline or uploaded and served from a website.

If you're looking to export an iPhoto library, try the earlier version of this software, [iPhoto Disc Export](https://github.com/cfinke/iPhoto-Disc-Export/).

This was put together for a one-time-use project, so some of the code was specific to my needs, but you might find it useful as a starting point, a.k.a. don't judge me by the messiness of this code.

Usage
=====

Run it like this:

```
$ ./photos-disc-export.php \
	--library=/path/to/photo/library \
	--output-dir=/path/for/exported/files \
	[--jpegrescan] \
	[--start-date=1950-01-01] \
	[--end-date=1980-01-01] \
	[--timezone="America/Los_Angeles"]
```

You may need to `chmod +x photos-disc-export.php` first.

Optional arguments:

* `--jpegrescan`: will invoke `jpegrescan` to optimize the size of the export photos. (Obviously, you must have (`jpegrescan`)[https://github.com/kud/jpegrescan] installed.) This will take a long time.
* `--start-date=YYYY-MM-DD`: will limit the export to photos from on or after that date.
* `--end-date=YYYY-MM-DD`: will limit the export to photos from on or before that date.
* `--timezone=[PHP timezone identifier]`: Sets the timezone that the script should use for considering photo dates.

You may specify multiple `--library` arguments to export photos from multiple libraries:

```
$ ./photos-disc-export.php \
	--library=/path/to/photo/library \
	--library=/path/to/photo/library2 \
	--output-dir=/path/for/exported/files \
	[--jpegrescan] \
	[--start-date=1950-01-01] \
	[--end-date=1980-01-01] \
	[--timezone="America/Los_Angeles"]
```

The Website
===========
The resulting website groups photos by the date on which they were taken and supports searching by date, title, description, and the names of people in the photos. Everything is ordered by date ascending. (Feel free to add sorting options and send me a patch.)

Here's how the main page of the website looks:

![All images laid out in a grid](screenshots/all.png)

Each photo shows the photo description, date, and list of people in the photo. Here's a photo page:

![Photo page with metadata](screenshots/photo.png)

The search query syntax supports JavaScript-style regex, quoted strings, and exclusions. For example, this:

`"Becky Smith" -"John Smith" [0-9]{4}-03`

would return all photos mentioning or including Becky Smith that do not mention or include John Smith that were taken in March of any year.

Misc
====

This repository contains a number of projects that are developed elsewhere:

* CSS and JS from the great [Studio theme by Pixel Union](http://studio-theme.pixelunion.net/) is used for the generated website.
* The jQuery lazyload plugin lives at http://www.appelsiini.net/projects/lazyload
* jQuery: http://jquery.com/
* modernizr.js: http://modernizr.com
* Lato fonts: http://www.latofonts.com/lato-free-fonts/
* Open Baskerville font: http://klepas.org/openbaskerville/
