### Discovery

Channel discovery is accomplished primarily through webfinger (RFC7033). You will be looking for an entry with 

````
rel: http://purl.org/zot/protocol/6.0
type: application/x-zot+json
href: (discovery rhef)
````

Load the url from the href using and HTTP Accept header of

````
Accept: application/x-zot+json;
````

This will provide a channel discovery document. It also includes a site discovery section.
You may load the site discovery packet separately by accessing the domain top level with 

````
Accept: application/x-zot+json;
````

Sample channel discovery document

````
{
    "id": "XSWtrP_U65k9ZXeBxoYbcBgy6cVteo3yLwmLy4ppkjXqAryky0pYBq8YWnj7rApoTSdgy-QeciQG7Yhz7QYt4g",
    "id_sig": "sha256.yG_Biu8pTpV0DlKPzoZHi0PbpM3okDYB7v5z2UuB___6J85gSOiOdes1tLwSmFkjbYMZbL2oksIe2tmD32lJWxpycSWJlDNbK8oggAtMx1sfVwyZOX_O0QBde2SxWCp0EIrRTRacIyKBzJhPRxCsGkc0uWin6XesXVZuYEVCxESr0KMT35Y79keOXGjJGv822C-Z2Nb4vphpbpftllGjxXOV70PxTNF0uZTWeVSmv2O0FGhkqBeBvBZU0FaWdYZqZZbd2AN_bto-8P95KMw6Fdfl2NIeL6vpD3xSu59Qhztl8L5npU13S3yvywzSvNg8DVgpNqcRmMiebaspfcjttCEAKtB2H-uiPkeuvDUk_iMXGtSUulcsNt1VFtSTnLEG371O6kj3dsczCV4QrpKBdIWNF3_41xHhrLi4Pug5JQg_wncyBSXu6Uj9pkCiD-JPVfI0ViCPccJcCKB-kXpP2EQIoPMhjV5x3bruI0TFLxrJqKWuoY6m8KUYrlGRdewaPYJ7pOY2NSNeLb9z6PO3UHT0bnr3DLyNxypxiUo5Pg4BxnHeuVKmiTxULF06KSwLmPDGsscrBSX1wIbHP6rhcmh0vDP0af2ixluLJbcLbptI2d137tFDVT4lTWBZ8PRNPWi1rfSl_x-dzevF8Dd3vi0iWd7D-aK89rqmRfUKsWk",
    "aliases": [
        "acct:zapper@zap.macgirvin.com",
        "https://zap.macgirvin.com/channel/zapper"
    ],
    "primary_location": {
        "address": "zapper@zap.macgirvin.com",
        "url": "https://zap.macgirvin.com/channel/zapper",
        "connections_url": "https://zap.macgirvin.com/poco/zapper",
        "follow_url": "https://zap.macgirvin.com/follow?f=&url=%s"
    },
    "public_key": "-----BEGIN PUBLIC KEY-----\nMIICIjANBgkqhkiG9w0BAQEFAAOCAg8AMIICCgKCAgEA154VVJChRzsdm1Aba4su\nMLhhnuTELsQCIuDGB07lxTlHmJeeD9eImLXzQPDTNlLcCYVQkKH1uPhfhwnBFITu\noG7hr9QklhFZxfe9IFiAfr7w1+IRFXCYEwfe/eAaYDoprNqtMxonniOiVvnAdNs+\nRi1/Bfasd9d4BRBSj2KwuAeTke7gvHlACRKmauhFmhfG2fzj53AL2ixsraBzdccR\nFseICC99eldgWk2Jg16J1Euh51HV56jUWoz4ZYb1Kxri8zf55R2GiQJCHvSCLlgl\noFaiHLPS+yBIlCkNZ+47ee6DL7ePJ6kip6+ukAP5/1vOM0ahTPANoqaWmg19RsOu\n2q0LPyotWyLX1JX13rtLC7qFlSST31gxY082G8QIJfEbWgYoci0g2XOCDjAZ3JjI\neK/+tZwLzrV5l5Tcorf35Kbc/lHbMSm+wuyc6eQV14tj542nUftZAgpkPOImHsRm\nLqwiG6uxpfIW0TFuUse45F/faYbV4pbcaGm7Hwp/MbXl8vNiE0LpV9eqZu5ryVjZ\nT+Vt32ISWuTeyLqB1Wb/lWP9lGsMFDtsiZ5Wst1W8omrAgYfYo4RxOr14TUiJg3T\nmgTg10fBrcTC210Kl0lKNkHGi0qK8LaB2QwuWy7FKgx5I7yhgWKSWLVFhIOlca/X\nFo0mxCf8NPqOrhxRdS6UeucCAwEAAQ==\n-----END PUBLIC KEY-----\n",
    "name": "Zapper",
    "name_updated": "2018-06-04 03:22:19",
    "username": "zapper",
    "photo": {
        "url": "https://zap.macgirvin.com/photo/profile/l/2",
        "type": "image/png",
        "updated": "2018-06-04 03:22:19"
    },
    "channel_role": "social",
    "searchable": true,
    "adult_content": false,
    "public_forum": false,
    "profile": {
        "description": "",
        "birthday": "",
        "gender": "",
        "marital": "",
        "sexual": "",
        "locale": "",
        "region": "",
        "postcode": "",
        "country": "",
        "about": "",
        "homepage": "",
        "hometown": ""
    },
    "permissions": "view_stream,view_profile,view_contacts,view_storage,view_pages,view_wiki",
    "permissions_for": "",
    "locations": [
        {
            "host": "zap.macgirvin.com",
            "address": "zapper@zap.macgirvin.com",
            "id_url": "https://zap.macgirvin.com/channel/zapper",
            "primary": true,
            "url": "https://zap.macgirvin.com",
            "url_sig": "sha256.qBKZU6tReyUkVcNgGldRfdINiPoBneN9wWc-RHN7CFj8z9GgRW26LDUgmWL6kNoobYvHO6VIdZLxJb6CGdTLs7pjYGMZeTxpHHTgo3uHdBBIJdWPAwyEoppKGR3qT3S5iYWW9P0dsMtGjQ_q2VdaiqguoG8Z3lnTWikT7ujPI4NXZP2R0PVzEmaefN4SXqTO22XhXO-SuK4EOHylGcusQCfO6hXji9KItfwH1rnPx588YNRQ9WvBkV95ArZYSELRoFuJfHWh4ABqqAwQ4BqTO4-Pv1LiN1bWoNwVTki79Lx2GhQlw-_7HcHtVpqW_TQ04G4iPXvWHLzKfErfbGnQ575sbsF1gc2MYOINCofOmTq8eU_HOWaGK8D10HxpCVMMZXK37i8b6QEk3wpCoGiStGe5nsytVepZwNhsdnmW5msyO2ew_jZo3t_lP7U3oRvJHyJ7JpZBZg4E-MkLa-00KtiVGIosCesmFbZ042OwwTH3iJeSgz-yxrOsg3xdCj3e7rx4E93ra91OdN-mL-x8K4iYjcPQ6UpjInx2qsc_B8qwiw7L4jqJan7SYRDlxSiAb3yd1NFqL5gEMBg7RkS9s9a92hU3R6_K6CPpP9fb4mzRAzxpgRMpzhmBKnzlWF_Uz6c0urQpJLqJxfV4OzFThyxuqi3UyPg_R59DY3JfDUI",
            "site_id": "gcwJ1OzIZbwtfgDcBYVYhwlUmjaxsgPyJezd-F2IS1F3IrlVsyOesNpm3hvoWemIBxoHmgIlMYKkhFeYihsqBQ",
            "callback": "https://zap.macgirvin.com/zot",
            "sitekey": "-----BEGIN PUBLIC KEY-----\nMIICIjANBgkqhkiG9w0BAQEFAAOCAg8AMIICCgKCAgEAuOkQslfq9EjZJLVniWP1\n3anzfdASnlgUKUYK1zyxy/HbwxAXl0GupYpJEVNIpkrGtTUMWY7ppxH7y/EAiSTJ\nsMIFIy1AnHgS/ecx6N/tH6rzZ68jD8yJQxjZBUk7MfhfYOK8KUti/qmp859Pr3cA\n1K1woCtSLRx2HNzHED8LDTUCGSwneHA2m7Ffc1MNfII8Ia/VtoF7pBwOixayws2N\nlY5syuDqOO3LtMJnDMBRN5WbtTw5jobyaoK6o4+Kg7Kln0nymloD3knFFsPvIdJd\nl493ItBi6k5QR0PV8NtElZMWRy8gZjzI5c4yukXku0WK1lVJBpjl4/LfjulWSMaR\nzj+YZKTjw7G56EEB2drNUVn/ZYLYwvMn6Bv/8lYu4VwL873UbupITNGX/Wh7+EU3\ntJqvXvdh8scIAKR3+sS/SrNI6OMn34HKewaX8iMf6NgW5lrskr9dhOYuZVr63huc\nhXxdGv+6b5+ARYEOpTn5QQd89WSL4Vui+1VaO4FARt9oRiC9s3sd0swyTxFqJSY4\nYJcpuVMUKY54jOGpsT0+1DlYFZf+lOk7pRpuYY1Vv/AhWCpkt6Uamf5d11rnVikA\nuPFgFqaObFenM1u1EKF1xrNaQqy3NbhOb0yRatVPcnAwOlesHbM7tgKmyZSopJTW\nJ2ug8isS+vNI0q+4IwET5FMCAwEAAQ==\n-----END PUBLIC KEY-----\n",
            "deleted": false
        }
    ],
    "site": {
        "url": "https://zap.macgirvin.com",
        "site_sig": "sha256.OWCNR-OocRwk2Y4RwSHtE-y_bcWPkXYPQetvRO-8VjO_b0eqwKDjvB52WPKKl43UQRHRn0CN6Xc487zPY7bVqIaLcsE23R0JOacl9IO3_9k-RbeH6H8KV8E_GynM-6cucPI1Dh7s3rgcqk-GXwoRaFaraurYDHvoGEVHOa1jHpv75lT2COCi1sDGhDR3-KPJbug61y58CUu3bJj2VRAqBaoiMz5TwbUIY9Sb22d205X_UzoIF_TlPDMoZv-Mbrkcxn9kgIfatgVyKGKKyoAnvyJeFzjHm1xCY4sZtt4C_em0a5wpcVPl31KbI5BodKn910ChErHXMCedBPeYWhRA0a-9Y_vYGonun3jXqJZ33WxzG9P1Gllp4bhxK6tm9X1iRpUnB7j8g8RHSH4PukQKSl2ErZ2vPLdHMIkczX9YEhhCbeZIvcX6T_5s82Ua75rlVktJGHsh8yLw3iqCdWljpCVhTWpEK0NmhJB6TcadE9qlRN9Gun7keEV4Ov6Dl5O7I-0ssoWbhv7lHU6JcjhAuf2TDLod_Izka32ZZk_8s8ZmqFzEEG6g6pRyuzvqk4XNK6cTL6dvBGIua5D0bRTBn4XTELx8u4B3yK7_MArr_m5Z5KfXOm_ngGMCN-lIZuKhxAQpCVDD5jcHmnCzjcDiR5m5LvOfvBSxwtpgHZUr3AU",
        "post": "https://zap.macgirvin.com/zot",
        "openWebAuth": "https://zap.macgirvin.com/owa",
        "authRedirect": "https://zap.macgirvin.com/magic",
        "sitekey": "-----BEGIN PUBLIC KEY-----\nMIICIjANBgkqhkiG9w0BAQEFAAOCAg8AMIICCgKCAgEAuOkQslfq9EjZJLVniWP1\n3anzfdASnlgUKUYK1zyxy/HbwxAXl0GupYpJEVNIpkrGtTUMWY7ppxH7y/EAiSTJ\nsMIFIy1AnHgS/ecx6N/tH6rzZ68jD8yJQxjZBUk7MfhfYOK8KUti/qmp859Pr3cA\n1K1woCtSLRx2HNzHED8LDTUCGSwneHA2m7Ffc1MNfII8Ia/VtoF7pBwOixayws2N\nlY5syuDqOO3LtMJnDMBRN5WbtTw5jobyaoK6o4+Kg7Kln0nymloD3knFFsPvIdJd\nl493ItBi6k5QR0PV8NtElZMWRy8gZjzI5c4yukXku0WK1lVJBpjl4/LfjulWSMaR\nzj+YZKTjw7G56EEB2drNUVn/ZYLYwvMn6Bv/8lYu4VwL873UbupITNGX/Wh7+EU3\ntJqvXvdh8scIAKR3+sS/SrNI6OMn34HKewaX8iMf6NgW5lrskr9dhOYuZVr63huc\nhXxdGv+6b5+ARYEOpTn5QQd89WSL4Vui+1VaO4FARt9oRiC9s3sd0swyTxFqJSY4\nYJcpuVMUKY54jOGpsT0+1DlYFZf+lOk7pRpuYY1Vv/AhWCpkt6Uamf5d11rnVikA\nuPFgFqaObFenM1u1EKF1xrNaQqy3NbhOb0yRatVPcnAwOlesHbM7tgKmyZSopJTW\nJ2ug8isS+vNI0q+4IwET5FMCAwEAAQ==\n-----END PUBLIC KEY-----\n",
        "directory_mode": "normal",
        "encryption": [
            "aes256ctr.oaep",
            "camellia256cfb.oaep",
            "cast5cfb.oaep"
        ],
        "zot": "6.1",
        "register_policy": "closed",
        "access_policy": "private",
        "accounts": 1,
        "channels": 3,
        "admin": "mike@macgirvin.com",
        "plugins": [],
        "sitehash": "c89e5a2b5059d04cc05078899c2083d4b89c190e6d6b247300256bfc66a930b3",
        "sitename": "Zap Development",
        "sellpage": "",
        "location": "",
        "realm": "RED_GLOBAL",
        "project": "zap",
        "version": "6.6"
    }
}
````