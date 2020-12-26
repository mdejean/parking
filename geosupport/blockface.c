#ifdef WIN32
#include "NYCgeo.h"
#else
#include "geo.h"
#define _GNU_SOURCE
#include <dlfcn.h>
#include <linux/limits.h>
#endif

#include "pac.h"

#include <stdio.h>
#include <string.h>
#include <stdlib.h>

 #define min(a,b) \
   ({ __typeof__ (a) _a = (a); \
       __typeof__ (b) _b = (b); \
     _a < _b ? _a : _b; })
 #define max(a,b) \
   ({ __typeof__ (a) _a = (a); \
       __typeof__ (b) _b = (b); \
     _a > _b ? _a : _b; })
   
#ifndef WIN32
#define NYCgeo geo
#endif

int main(int argc, char** argv) {
    C_WA1 wa1 = {};
    C_WA2_F3CX wa2 = {};
    
    wa1.input.platform_ind = 'P';
    
    if (argc < 5) {
        puts("blockface borough_code on_street from_street to_street side_of_street [from_direction [to_direction]]");
        return 1;
    }
    
#ifndef WIN32
    Dl_info d = {};
    if (dladdr(geo, &d)) {
        char* last_sep;
        last_sep = strrchr(d.dli_fname, '/');
        if (last_sep) {
            last_sep = (char*)memrchr(d.dli_fname, '/', last_sep - d.dli_fname);
            if (last_sep && last_sep - d.dli_fname + 6 < PATH_MAX) {
                char p[PATH_MAX] = {};
                memcpy(p, d.dli_fname, last_sep - d.dli_fname);
                strcpy(&p[last_sep - d.dli_fname], "/fls/");
                setenv("GEOFILES", p, 0);
            }
        }
    }
#endif
    
    wa1.input.func_code[0] = '3';
    wa1.input.func_code[1] = 'C';
    wa1.input.mode_switch  = 'X';
    
    char boro = argv[1][0];
    
    wa1.input.sti[0].boro = boro;
    memcpy(wa1.input.sti[0].Street_name, 
           argv[2], 
           min(sizeof(wa1.input.sti[0].Street_name), strlen(argv[2])) );
    if (argc > 3) {
        wa1.input.sti[1].boro = boro;
        memcpy(wa1.input.sti[1].Street_name, 
               argv[3], 
               min(sizeof(wa1.input.sti[1].Street_name), strlen(argv[3])) );
        
        wa1.input.sti[2].boro = boro;
        memcpy(wa1.input.sti[2].Street_name, 
               argv[4], 
               min(sizeof(wa1.input.sti[2].Street_name), strlen(argv[4])) );
        
        if (argc > 5) {
            wa1.input.comp_direction = argv[5][0];
        }
        if (argc > 6) {
            wa1.input.comp_direction2 = argv[6][0];
        }
    }
    
    NYCgeo((char*)&wa1, (char*)&wa2);
    
    if ((memcmp(wa1.output.ret_code, "00", 2) == 0) ||
        (memcmp(wa1.output.ret_code, "01", 2) == 0)) {
        if (wa2.cwa2f3c.x_street_reversal_flag == 'R') {
            printf("-");
        }
        printf("%.10s",wa2.blockface_id);
    } else {
        printf("{\"error_code\": \"%c%c\", \"error_message\": \"%.80s\"}",
            wa1.output.ret_code[1], wa1.output.ret_code[0],
            wa1.output.msg);
    }
    
    return 0;
}
