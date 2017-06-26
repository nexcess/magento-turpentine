vcl 4.0;
# Nexcess.net Turpentine Extension for Magento
# Copyright (C) 2012  Nexcess.net L.L.C.
#
# This program is free software; you can redistribute it and/or modify
# it under the terms of the GNU General Public License as published by
# the Free Software Foundation; either version 2 of the License, or
# (at your option) any later version.
#
# This program is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License along
# with this program; if not, write to the Free Software Foundation, Inc.,
# 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.

## Nexcessnet_Turpentine Varnish v4 VCL Template

## Custom C Code

C{
    // @source app/code/community/Nexcessnet/Turpentine/misc/uuid.c
    {{custom_c_code}}
}C

## Imports

import std;
import directors;

## Custom VCL Logic - Top

{{custom_vcl_include_top}}

## Backends

{{default_backend}}

{{admin_backend}}

## ACLs

{{crawler_acl}}

{{debug_acl}}

## Custom Subroutines

{{generate_session_start}}
sub generate_session {
    # generate a UUID and add `frontend=$UUID` to the Cookie header, or use SID
    # from SID URL param
    if (req.url ~ ".*[&?]SID=([^&]+).*") {
        set req.http.X-Varnish-Faked-Session = regsub(
            req.url, ".*[&?]SID=([^&]+).*", "frontend=\1");
    } else {
        C{
            char uuid_buf [50];
            generate_uuid(uuid_buf);
	        static const struct gethdr_s VGC_HDR_REQ_VARNISH_FAKED_SESSION =
            { HDR_REQ, "\030X-Varnish-Faked-Session:"};
            VRT_SetHdr(ctx,
                &VGC_HDR_REQ_VARNISH_FAKED_SESSION,
                uuid_buf,
                vrt_magic_string_end
            );
        }C
    }
    if (req.http.Cookie) {
        # client sent us cookies, just not a frontend cookie. try not to blow
        # away the extra cookies
        std.collect(req.http.Cookie);
        set req.http.Cookie = req.http.X-Varnish-Faked-Session +
            "; " + req.http.Cookie;
    } else {
        set req.http.Cookie = req.http.X-Varnish-Faked-Session;
    }
}

sub generate_session_expires {
    # sets X-Varnish-Cookie-Expires to now + esi_private_ttl in format:
    #   Tue, 19-Feb-2013 00:14:27 GMT
    # this isn't threadsafe but it shouldn't matter in this case
    C{
        time_t now = time(NULL);
        struct tm now_tm = *gmtime(&now);
        now_tm.tm_sec += {{esi_private_ttl}};
        mktime(&now_tm);
        char date_buf [50];
        strftime(date_buf, sizeof(date_buf)-1, "%a, %d-%b-%Y %H:%M:%S %Z", &now_tm);
	    static const struct gethdr_s VGC_HDR_RESP_COOKIE_EXPIRES =
        { HDR_RESP, "\031X-Varnish-Cookie-Expires:"};
        VRT_SetHdr(ctx,
            &VGC_HDR_RESP_COOKIE_EXPIRES,
            date_buf,
            vrt_magic_string_end
        );
    }C
}
{{generate_session_end}}
## Varnish Subroutines

sub vcl_init {
    {{directors}}
}

sub vcl_recv {
	{{maintenance_allowed_ips}}

    {{https_proto_fix}}
    {{https_redirect}}

    # this always needs to be done so it's up at the top
    if (req.restarts == 0) {
        if (req.http.X-Forwarded-For) {
            set req.http.X-Forwarded-For =
                req.http.X-Forwarded-For + ", " + client.ip;
        } else {
            set req.http.X-Forwarded-For = client.ip;
        }
    }

    # We only deal with GET and HEAD by default
    # we test this here instead of inside the url base regex section
    # so we can disable caching for the entire site if needed
    if (!{{enable_caching}} || req.http.Authorization ||
        req.method !~ "^(GET|HEAD|OPTIONS)$" ||
        req.http.Cookie ~ "varnish_bypass={{secret_handshake}}") {
        return (pipe);
    }

    if({{send_unmodified_url}}) {
        # save the unmodified url
        set req.http.X-Varnish-Origin-Url = req.url;
    }

    # remove double slashes from the URL, for higher cache hit rate
    set req.url = regsuball(req.url, "(.*)//+(.*)", "\1/\2");

    {{normalize_encoding}}
    {{normalize_user_agent}}
    {{normalize_host}}

    # check if the request is for part of magento
    if (req.url ~ "{{url_base_regex}}") {
        # set this so Turpentine can see the request passed through Varnish
        set req.http.X-Turpentine-Secret-Handshake = "{{secret_handshake}}";
        # use the special admin backend and pipe if it's for the admin section
        if (req.url ~ "{{url_base_regex}}{{admin_frontname}}") {
            set req.backend_hint = {{admin_backend_hint}};
            return (pipe);
        } else {
            {{set_backend_hint}}
        }
        if (req.http.Cookie ~ "\bcurrency=") {
            set req.http.X-Varnish-Currency = regsub(
                req.http.Cookie, ".*\bcurrency=([^;]*).*", "\1");
        }
        if (req.http.Cookie ~ "\bstore=") {
            set req.http.X-Varnish-Store = regsub(
                req.http.Cookie, ".*\bstore=([^;]*).*", "\1");
        }
        # looks like an ESI request, add some extra vars for further processing
        if (req.url ~ "/turpentine/esi/get(?:Block|FormKey)/") {
            set req.http.X-Varnish-Esi-Method = regsub(
                req.url, ".*/{{esi_method_param}}/(\w+)/.*", "\1");
            set req.http.X-Varnish-Esi-Access = regsub(
                req.url, ".*/{{esi_cache_type_param}}/(\w+)/.*", "\1");

            # throw a forbidden error if debugging is off and a esi block is
            # requested by the user (does not apply to ajax blocks)
            if (req.http.X-Varnish-Esi-Method == "esi" && req.esi_level == 0 &&
                    !({{debug_headers}} || client.ip ~ debug_acl)) {
                return (synth(403, "External ESI requests are not allowed"));
            }
        }
        {{allowed_hosts}}
        # no frontend cookie was sent to us AND this is not an ESI or AJAX call
        if (req.http.Cookie !~ "frontend=" && !req.http.X-Varnish-Esi-Method) {
            if (client.ip ~ crawler_acl ||
                    req.http.User-Agent ~ "^(?:{{crawler_user_agent_regex}})$") {
                # it's a crawler, give it a fake cookie
                set req.http.Cookie = "frontend=crawler-session";
            } else {
                # it's a real user, make up a new session for them
                {{generate_session}}
            }
        }
        if ({{force_cache_static}} &&
                req.url ~ ".*\.(?:{{static_extensions}})(?=\?|&|$)") {
            # don't need cookies for static assets
            unset req.http.Cookie;
            unset req.http.X-Varnish-Faked-Session;
            set req.http.X-Varnish-Static = 1;
            return (hash);
        }
        # this doesn't need a enable_url_excludes because we can be reasonably
        # certain that cron.php at least will always be in it, so it will
        # never be empty
        if (req.url ~ "{{url_base_regex}}(?:{{url_excludes}})" ||
                # user switched stores. we pipe this instead of passing below because
                # switching stores doesn't redirect (302), just acts like a link to
                # another page (200) so the Set-Cookie header would be removed
                req.url ~ "\?.*__from_store=") {
            return (pipe);
        }
        if ({{enable_get_excludes}} &&
                req.url ~ "(?:[?&](?:{{get_param_excludes}})(?=[&=]|$))") {
            # TODO: should this be pass or pipe?
            return (pass);
        }
        if (req.url ~ "[?&](utm_source|utm_medium|utm_campaign|gclid|cx|ie|cof|siteurl)=") {
            # Strip out Google related parameters
            set req.url = regsuball(req.url, "(?:(\?)?|&)(?:utm_source|utm_medium|utm_campaign|gclid|cx|ie|cof|siteurl)=[^&]+", "\1");
            set req.url = regsuball(req.url, "(?:(\?)&|\?$)", "\1");
        }

        if ({{enable_get_ignored}} && req.url ~ "[?&]({{get_param_ignored}})=") {
            # Strip out Ignored GET parameters
            set req.url = regsuball(req.url, "(?:(\?)?|&)(?:{{get_param_ignored}})=[^&]+", "\1");
            set req.url = regsuball(req.url, "(?:(\?)&|\?$)", "\1");
        }

        if({{send_unmodified_url}}) {
            set req.http.X-Varnish-Cache-Url = req.url;
            set req.url = req.http.X-Varnish-Origin-Url;
            unset req.http.X-Varnish-Origin-Url;
        }

        # everything else checks out, try and pull from the cache
        return (hash);
    }
    # else it's not part of magento so do default handling (doesn't help
    # things underneath magento but we can't detect that)
}

sub vcl_pipe {
    # since we're not going to do any stuff to the response we pretend the
    # request didn't pass through Varnish
    unset bereq.http.X-Turpentine-Secret-Handshake;
    set bereq.http.Connection = "close";
}

# sub vcl_pass {
#     return (pass);
# }

sub vcl_hash {
    std.log("vcl_hash start");

    # For static files we keep the hash simple and don't add the domain.
    # This saves memory when a static file is used on multiple domains.
    if ({{simple_hash_static}} && req.http.X-Varnish-Static) {
        std.log("hash_data static file - req.url: " + req.url);
        hash_data(req.url);
        if (req.http.Accept-Encoding) {
            # make sure we give back the right encoding
            std.log("hash_data static file - Accept-Encoding: " + req.http.Accept-Encoding);
            hash_data(req.http.Accept-Encoding);
        }
        std.log("vcl_hash end return lookup");
        return (lookup);
    }


    if({{send_unmodified_url}} && req.http.X-Varnish-Cache-Url) {
        hash_data(req.http.X-Varnish-Cache-Url);
        std.log("hash_data - X-Varnish-Cache-Url: " + req.http.X-Varnish-Cache-Url);
    } else {
        hash_data(req.url);
        std.log("hash_data - req.url: " + req.url );
    }

    if (req.http.Host) {
        hash_data(req.http.Host);
        std.log("hash_data - req.http.Host: " + req.http.Host);
    } else {
        hash_data(server.ip);
    }

    std.log("hash_data - req.http.Ssl-Offloaded: " + req.http.Ssl-Offloaded);
    hash_data(req.http.Ssl-Offloaded);

    if (req.http.X-Normalized-User-Agent) {
        hash_data(req.http.X-Normalized-User-Agent);
        std.log("hash_data - req.http.X-Normalized-User-Agent: " + req.http.X-Normalized-User-Agent);
    }
    if (req.http.Accept-Encoding) {
        # make sure we give back the right encoding
        hash_data(req.http.Accept-Encoding);
        std.log("hash_data - req.http.Accept-Encoding: " + req.http.Accept-Encoding);
    }
    if (req.http.X-Varnish-Store || req.http.X-Varnish-Currency) {
        # make sure data is for the right store and currency based on the *store*
        # and *currency* cookies
        hash_data("s=" + req.http.X-Varnish-Store + "&c=" + req.http.X-Varnish-Currency);
        std.log("hash_data - Store and Currency: " + "s=" + req.http.X-Varnish-Store + "&c=" + req.http.X-Varnish-Currency);
    }

    if (req.http.X-Varnish-Esi-Access == "private" &&
            req.http.Cookie ~ "frontend=") {
        std.log("hash_data - frontned cookie: " + regsub(req.http.Cookie, "^.*?frontend=([^;]*);*.*$", "\1"));
        hash_data(regsub(req.http.Cookie, "^.*?frontend=([^;]*);*.*$", "\1"));
        {{advanced_session_validation}}

    }
    
    if (req.http.X-Varnish-Esi-Access == "customer_group" &&
            req.http.Cookie ~ "customer_group=") {
        hash_data(regsub(req.http.Cookie, "^.*?customer_group=([^;]*);*.*$", "\1"));
    }
    std.log("vcl_hash end return lookup");
    return (lookup);
}

sub vcl_hit {
    # this seems to cause cache object contention issues so removed for now
    # TODO: use obj.hits % something maybe
    # if (obj.hits > 0) {
    #     set obj.ttl = obj.ttl + {{lru_factor}}s;
    # }
}

# sub vcl_miss {
#     return (fetch);
# }

sub vcl_backend_response {
    # set the grace period
    set beresp.grace = {{grace_period}}s;

    # Store the URL in the response object, to be able to do lurker friendly bans later
    set beresp.http.X-Varnish-Host = bereq.http.host;
    set beresp.http.X-Varnish-URL = bereq.url;

    # if it's part of magento...
    if (bereq.url ~ "{{url_base_regex}}") {
        # we handle the Vary stuff ourselves for now, we'll want to actually
        # use this eventually for compatibility with downstream proxies
        # TODO: only remove the User-Agent field from this if it exists
        unset beresp.http.Vary;
        # we pretty much always want to do this
        set beresp.do_gzip = true;

        if (beresp.status != 200 && beresp.status != 404) {
            # pass anything that isn't a 200 or 404
            set beresp.ttl = {{grace_period}}s;
            set beresp.uncacheable = true;
            return (deliver);
        } else {
            # if Magento sent us a Set-Cookie header, we'll put it somewhere
            # else for now
            if (beresp.http.Set-Cookie) {
                set beresp.http.X-Varnish-Set-Cookie = beresp.http.Set-Cookie;
                unset beresp.http.Set-Cookie;
            }

            # we'll set our own cache headers if we need them
            # we'll override the "Cache-Control" header if needed
            unset beresp.http.Expires;
            unset beresp.http.Pragma;
            unset beresp.http.Cache;
            unset beresp.http.Age;

            if (beresp.http.X-Turpentine-Esi == "1") {
                set beresp.do_esi = true;
            }
            if (beresp.http.X-Turpentine-Cache == "0") {
                set beresp.ttl = {{grace_period}}s;
                set beresp.uncacheable = true;
                set beresp.http.Cache-Control = "no-store, no-cache, must-revalidate";
                return (deliver);
            } else {
                if ({{force_cache_static}} &&
                        bereq.url ~ ".*\.(?:{{static_extensions}})(?=\?|&|$)") {
                    # it's a static asset
                    set beresp.ttl = {{static_ttl}}s;
                    set beresp.http.Cache-Control = "max-age={{static_ttl}}";
                } elseif (bereq.http.X-Varnish-Esi-Method) {
                    # it's a ESI request
                    if (bereq.http.X-Varnish-Esi-Access == "private" &&
                            bereq.http.Cookie ~ "frontend=") {
                        # set this header so we can ban by session from Turpentine
                        set beresp.http.X-Varnish-Session = regsub(bereq.http.Cookie,
                            "^.*?frontend=([^;]*);*.*$", "\1");
                    }
                    if (bereq.http.X-Varnish-Esi-Method == "ajax" &&
                            bereq.http.X-Varnish-Esi-Access == "public") {
                        set beresp.http.Cache-Control = "max-age=" + regsub(
                            bereq.url, ".*/{{esi_ttl_param}}/(\d+)/.*", "\1");
                    }
                    set beresp.ttl = std.duration(
                        regsub(
                            bereq.url, ".*/{{esi_ttl_param}}/(\d+)/.*", "\1s"),
                        300s);
                    if (beresp.ttl == 0s) {
                        # this is probably faster than bothering with 0 ttl
                        # cache objects
                        set beresp.ttl = {{grace_period}}s;
                        set beresp.uncacheable = true;
                        set beresp.http.Cache-Control = "no-store, no-cache, must-revalidate";
                        return (deliver);
                    }
                } else {
                    {{url_ttls}}
                }
            }
        }
        # we've done what we need to, send to the client
        return (deliver);
    }
    # else it's not part of Magento so use the default Varnish handling
}

{{vcl_synth}}

sub vcl_deliver {
    if (req.http.X-Varnish-Faked-Session) {
        # need to set the set-cookie header since we just made it out of thin air
        {{generate_session_expires}}
        set resp.http.Set-Cookie = req.http.X-Varnish-Faked-Session +
            "; expires=" + resp.http.X-Varnish-Cookie-Expires + "; path=/";
        if (req.http.Host) {
            if (req.http.User-Agent ~ "^(?:{{crawler_user_agent_regex}})$") {
                # it's a crawler, no need to share cookies
                set resp.http.Set-Cookie = resp.http.Set-Cookie +
                "; domain=" + regsub(req.http.Host, ":\d+$", "");
            } else {
                # it's a real user, allow sharing of cookies between stores
                if (req.http.Host ~ "{{normalize_cookie_regex}}" && "{{normalize_cookie_regex}}" ~ "..") {
                    set resp.http.Set-Cookie = resp.http.Set-Cookie +
                    "; domain={{normalize_cookie_target}}";
                } else {
                    set resp.http.Set-Cookie = resp.http.Set-Cookie +
                    "; domain=" + regsub(req.http.Host, ":\d+$", "");
                }
            }
        }
        set resp.http.Set-Cookie = resp.http.Set-Cookie + "; httponly";
        unset resp.http.X-Varnish-Cookie-Expires;
    }
    if (req.http.X-Varnish-Esi-Method == "ajax" && req.http.X-Varnish-Esi-Access == "private") {
        set resp.http.Cache-Control = "no-cache";
    }
    if ({{debug_headers}} || client.ip ~ debug_acl) {
        # debugging is on, give some extra info
        set resp.http.X-Varnish-Hits = obj.hits;
        set resp.http.X-Varnish-Esi-Method = req.http.X-Varnish-Esi-Method;
        set resp.http.X-Varnish-Esi-Access = req.http.X-Varnish-Esi-Access;
        set resp.http.X-Varnish-Currency = req.http.X-Varnish-Currency;
        set resp.http.X-Varnish-Store = req.http.X-Varnish-Store;
    } else {
        # remove Varnish fingerprints
        unset resp.http.X-Varnish;
        unset resp.http.Via;
        unset resp.http.X-Powered-By;
        unset resp.http.Server;
        unset resp.http.X-Turpentine-Cache;
        unset resp.http.X-Turpentine-Esi;
        unset resp.http.X-Turpentine-Flush-Events;
        unset resp.http.X-Turpentine-Block;
        unset resp.http.X-Varnish-Session;
        unset resp.http.X-Varnish-Host;
        unset resp.http.X-Varnish-URL;
        # this header indicates the session that originally generated a cached
        # page. it *must* not be sent to a client in production with lax
        # session validation or that session can be hijacked
        unset resp.http.X-Varnish-Set-Cookie;
    }
}

## Custom VCL Logic - Bottom

{{custom_vcl_include}}
