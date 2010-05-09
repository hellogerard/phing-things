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
- **SmartyCompileTask** - compiles Smarty templates - _experimental_.

Filters
-------

These filters run together dynamically transform a static site into one
that conforms to many of Yahoo's rules for Exception Performance
<http://developer.yahoo.com/performance/>. Namely, it takes CSS and Javascript
files and:

- combines multiple files into a single static resource
- minifies files using YUI Compressor
- adds a timestamp to the filename for long-lived Expires headers

And takes images and:

- optimizes images with optimization programs (optipng, jpegtran)
- adds a timestamp to the filename for long-lived Expires headers

This process is outlined in
<http://www.slideshare.net/gerrys0/front-end-website-optimization-presentation>.

Filters:

- **OptimizeCSS** - runs CSS files thru Yahoo's Front End Rules for Exceptional Performance.
- **OptimizeJS** - runs Javascript files thru Yahoo's Front End Rules for Exceptional Performance.
- **OptimizeImages** - runs image files thru Yahoo's Front End Rules for Exceptional Performance.

There is an example website called `mysite`. Running `phing optimize` will
optimize `mysite` and create a `build` site. The new website should be
identical but optimized for the front-end.

Apache configuration
--------------------

For further optimizations, make these changes to your Apache configuration
(`httpd.conf` or `vhosts` file). 

    # turn on compression.
    AddOutputFilterByType DEFLATE text/html text/css text/plain text/xml application/x-javascript
    # modify `ETags`. 
    FileETag MTime Size
    # turn off `.htaccess` files.
    AllowOverride None
    # set `Expires` headers for static resources far in the future.
    ExpiresActive On
    ExpiresByType text/css "access plus 1 year"
    ExpiresByType application/x-javascript "accss plus 1 year"
    ExpiresByType image/jpeg "access plus 1 yea"
    ExpiresByType image/gif "access plus 1 year
    ExpiresByType image/png "access plus 1 year
    # Turn on `HTTP Keep-Alive` for static resources. Be very careful with
    # `KeepAliveTimeout`. Setting it too high will cause Apache to consumer ll the
    # memory on a machine very quickly.
    KeepAlive On
    KeepAliveTimeout 2


_see LICENSE for copyright and license info_
