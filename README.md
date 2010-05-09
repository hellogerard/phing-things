Phing Things
============


Various tasks, filters, and mappers for phing <<http://phing.info>>. See
`build.xml` for additional documentation.

Tasks
-----

- **CheckstyleReportTask** - create Checkstyle reports from XML files.
- **ConsistenCopy** - exactly like CopyTask, only copies to a tmp file and then does an atomic rename.
- **ForkPhingTask** - runs phing build files in subdirectories/subprojects.
- **MinifyJSTask** - runs YUI Compressor on Javascript files.
- **OptimizeImagesTask** - runs image optimization programs (jpegtran, optipng) on images.
- **SmartyCompileTask** - compiles Smarty templates.

Filters
-------

The following filters run together dynamically transform a static site into one
that conforms to many of Yahoo's rules for Exception Performance. Namely, it
takes CSS and Javascript files and:

- combines multiple files into a single static resource
- compresses files using gzip (done in Apache)
- minifies files using YUI Compressor
- adds a timestamp to the filename for long-lived Expires headers

And takes images and:

- optimizes images with optimization programs (optipng, jpegtran)
- adds a timestamp to the filename for long-lived Expires headers

**Filters:**

- **OptimizeCSS** - runs CSS files thru Yahoo's Front End Rules for Exceptional Performance.
- **OptimizeJS** - runs Javascript files thru Yahoo's Front End Rules for Exceptional Performance.
- **OptimizeImages** - runs image files thru Yahoo's Front End Rules for Exceptional Performance.

There is an example website called `mysite`. Running `phing optimize` will
optimize `mysite` and create a `build` directory. The new website should be
identical but optimized for the front-end.


_see LICENSE for copyright and license info_
