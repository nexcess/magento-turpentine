## Nexcessnet_Turpentine Varnish v3 VCL Template

## Backends

{{default_backend}}

{{admin_backend}}

## Custom Subroutines
sub remove_cache_headers {
    unset beresp.http.Set-Cookie;
    unset beresp.http.Cache-Control;
    unset beresp.http.Expires;
    unset beresp.http.Pragma;
    unset beresp.http.Cache;
    unset beresp.http.Age;
}

## Varnish Subroutines

sub vcl_recv {
    if (req.restarts == 0) {
        if (req.http.X-Forwarded-For) {
            set req.http.X-Forwarded-For =
                req.http.X-Forwarded-For + ", " + client.ip;
        } else {
            set req.http.X-Forwarded-For = client.ip;
        }
    }

    if (req.request != "GET" &&
            req.request != "HEAD" &&
            req.request != "PUT" &&
            req.request != "POST" &&
            req.request != "TRACE" &&
            req.request != "DELETE" &&
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

    #GCC should completely optimize any "false && <cond>" branches away, hopefully
    if (!{{enable_caching}}) {
        return (pass);
    }
    if (req.url ~ "{{url_base_regex}}{{admin_frontname}}") {
        set req.backend = admin;
        return (pass);
    }
    if (req.url ~ "{{url_base_regex}}") {
        if ({{force_cache_static}} &&
                req.url ~ ".*\.(?:{{static_extensions}})(?=\?|$)") {
            unset req.http.Cookie;
            return (lookup);
        }
        if (req.url ~ "{{url_base_regex}}(?:{{url_excludes}})") {
            return (pass);
        }
        if (req.http.Cookie ~ "{{cookie_excludes}}") {
            return (pass);
        }
        if ({{enable_get_excludes}} &&
                req.url ~ "(?:[?&](?:{{get_param_excludes}})(?=[&=]|$))") {
            return (pass);
        }
        if ({{set_initial_cookie}}) {
            if (req.http.Cookie && req.http.Cookie ~ "frontend=") {
                set req.http.X-Varnish-Cookie = req.http.Cookie;
                unset req.http.Cookie;
                return (lookup);
            } else {
                return (pass);
            }
        } else {
            unset req.http.Cookie;
            return (lookup);
        }
    }
    # else it's not part of magento so do default handling (doesn't help
    # things underneath magento but we can't detect that)
}

sub vcl_pipe {
    set req.http.Connection = "close";
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
    set req.grace = {{grace_period}}s;
    unset beresp.http.Vary;

    #GCC should optimize this entire branch away if static caching is disabled
    if ({{force_cache_static}} && bereq.url ~ ".*\.(?:{{static_extensions}})(?=\?|$)") {
        call remove_cache_headers;
        set beresp.ttl = {{static_ttl}}s;
    } else if (req.http.Cookie ~ "{{cookie_excludes}}" ||
        beresp.http.Set-Cookie ~ "{{cookie_excludes}}") {
        return (deliver);
    } else if (beresp.http.X-Varnish-Bypass) {
        set beresp.ttl = {{grace_period}}s;
        return (hit_for_pass);
    } else {
        if ({{set_initial_cookie}}) {
            if (req.http.X-Varnish-Cookie) {
                call remove_cache_headers;
                {{url_ttls}}
            }
        } else {
            call remove_cache_headers;
            {{url_ttls}}
        }
    }
    return (deliver);
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

#https://www.varnish-cache.org/trac/wiki/VCLExampleHitMissHeader
sub vcl_deliver {
    #GCC should optimize this entire branch away if debug headers are disabled
    if ({{debug_headers}}) {
        if (obj.hits > 0) {
            set resp.http.X-Varnish-Hits = "HIT: " + obj.hits;
        } else {
            set resp.http.X-Varnish-Hits = "MISS";
        }
    } else {
        #remove Varnish fingerprints
        unset resp.http.X-Varnish;
        unset resp.http.Via;
        unset resp.http.X-Powered-By;
        unset resp.http.Server;
        unset resp.http.Age;
    }
}

sub vcl_error {
    #GCC should optimize this entire branch away if debug headers are disabled
    if (!{{debug_headers}}) {
        unset obj.http.Server;
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
