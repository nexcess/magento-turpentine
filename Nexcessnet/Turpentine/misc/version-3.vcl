## Nexcessnet_Turpentine Varnish v3 VCL Template

## Backends

{{default_backend}}

## ACLs

{{purge_acl}}

## Custom Subroutines
#https://www.varnish-cache.org/trac/wiki/VCLExampleNormalizeUserAgent
sub normalize_user_agent {
    if (req.http.User-Agent ~ "MSIE") {
        set req.http.X-Normalized-User-Agent = "msie";
    } else if (req.http.User-Agent ~ "Firefox") {
        set req.http.X-Normalized-User-Agent = "firefox";
    } else if (req.http.User-Agent ~ "Safari") {
        set req.http.X-Normalized-User-Agent = "safari";
    } else if (req.http.User-Agent ~ "Chrome") {
        set req.http.X-Normalized-User-Agent = "chrome";
    } else if (req.http.User-Agent ~ "Opera Mini/") {
        set req.http.X-Normalized-User-Agent = "opera-mini";
    } else if (req.http.User-Agent ~ "Opera Mobi/") {
        set req.http.X-Normalized-User-Agent = "opera-mobile";
    } else if (req.http.User-Agent ~ "Opera") {
        set req.http.X-Normalized-User-Agent = "opera";
    } else {
        set req.http.X-Normalized-User-Agent = "nomatch";
    }
}

#https://www.varnish-cache.org/trac/wiki/VCLExampleNormalizeAcceptEncoding
sub normalize_encoding {
    if (req.http.Accept-Encoding) {
        if (req.http.Accept-Encoding ~ "gzip") {
            set req.http.Accept-Encoding = "gzip";
        } else if (req.http.Accept-Encoding ~ "deflate") {
            set req.http.Accept-Encoding = "deflate";
        } else {
            # unkown algorithm
            unset req.http.Accept-Encoding;
        }
    }
}

sub normalize_host {
    if (req.http.Host) {
        if(req.http.Host !~ "^{{normalize_host_target}}$") {
            set req.http.Host = "{{normalize_host_target}}";
        }
    }
}

## Varnish Subroutines

sub vcl_recv {
    if (req.restarts == 0) {
        if (req.http.x-forwarded-for) {
            set req.http.X-Forwarded-For =
                req.http.X-Forwarded-For + ", " + client.ip;
        } else {
            set req.http.X-Forwarded-For = client.ip;
        }
    }

    # this will need to be changed to a custom verb (PURGE?) if Magento ever
    # starts using DELETE
    if (req.request == "DELETE") {
        if (!client.ip ~ purge_trusted) {
            error 405 "Not Allowed";
        } else {
            ban_url(req.url);
            error 200 "Purged: " + req.url;
        }
    }

    if (req.request != "GET" &&
            req.request != "HEAD" &&
            req.request != "PUT" &&
            req.request != "POST" &&
            req.request != "TRACE" &&
            req.request != "OPTIONS") {
        /* Non-RFC2616 or CONNECT which is weird. */
        return (pipe);
    }

    if (req.request != "GET" && req.request != "HEAD") {
        /* We only deal with GET and HEAD by default */
        return (pass);
    }

    {{normalize_encoding}}
    {{normalize_user_agent}}
    {{normalize_host}}

    if (req.url ~ "^{{url_base}}(?:(?:index|litespeed)\.php/)?(?:{{url_includes}})") {
        if (req.url ~ "^{{url_base}}(?:(?:index|litespeed)\.php/)?(?:{{url_excludes}})") {
            return (pass);
        } else {
            if (req.http.cookie ~ "varnish_nocache") {
                return (pass);
            } else {
                if (req.url ~ "(?:[?&](?:{{get_excludes}})(?=[&=]|$))") {
                    return (pass);
                } else {
                    unset req.http.Cookie;
                    return (lookup);
                }
            }
        }
    } else {
        return (pass);
    }
}

sub vcl_pipe {
    set req.http.connection = "close";
    return (pipe);
}

# sub vcl_pass {
#     return (pass);
# }

sub vcl_hash {
    hash_data(req.url);
    if (req.http.Host) {
        hash_data(req.http.Host);
    } else {
        hash_data(server.ip);
    }
    if (req.http.X-Normalized-User-Agent) {
        hash_data(req.http.X-Normalized-User-Agent);
    }
    if (req.http.Accept-Encoding) {
        hash_data(req.http.Accept-Encoding);
    }
    return (hash);
}

# sub vcl_hit {
#     return (deliver);
# }
#
# sub vcl_miss {
#     return (fetch);
# }
#

sub vcl_fetch {
    set req.grace = 30s;

    if (req.http.Cookie ~ "varnish_nocache" ||
        beresp.http.Set-Cookie ~ "varnish_nocache") {
        return (deliver);
    } else if (beresp.http.X-Varnish-Bypass) {
        return (deliver);
    } else {
        unset beresp.http.Set-Cookie;
        unset beresp.http.Cache-Control;
        unset beresp.http.Expires;
        unset beresp.http.Pragma;
        unset beresp.http.Cache;
        unset beresp.http.Age;
        set beresp.ttl = 5m;
    }
}

# sub vcl_fetch {
#     if (beresp.ttl <= 0s ||
#         beresp.http.Set-Cookie ||
#         beresp.http.Vary == "*") {
# 		/*
# 		 * Mark as "Hit-For-Pass" for the next 2 minutes
# 		 */
# 		set beresp.ttl = 120 s;
# 		return (hit_for_pass);
#     }
#     return (deliver);
# }

sub vcl_deliver {
    if (obj.hits > 0) {
        set resp.http.X-Varnish-Cache = "HIT: " + obj.hits;
    } else {
        set resp.http.X-Varnish-Cache = "MISS";
    }
}

# sub vcl_error {
#     set obj.http.Content-Type = "text/html; charset=utf-8";
#     set obj.http.Retry-After = "5";
#     synthetic {"
# <?xml version="1.0" encoding="utf-8"?>
# <!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN"
#  "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
# <html>
#   <head>
#     <title>"} + obj.status + " " + obj.response + {"</title>
#   </head>
#   <body>
#     <h1>Error "} + obj.status + " " + obj.response + {"</h1>
#     <p>"} + obj.response + {"</p>
#     <h3>Guru Meditation:</h3>
#     <p>XID: "} + req.xid + {"</p>
#     <hr>
#     <p>Varnish cache server</p>
#   </body>
# </html>
# "};
#     return (deliver);
# }
#
# sub vcl_init {
# 	return (ok);
# }
#
# sub vcl_fini {
# 	return (ok);
# }
