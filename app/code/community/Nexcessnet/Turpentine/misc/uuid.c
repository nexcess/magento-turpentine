#include <stdlib.h>
#include <stdio.h>
#include <time.h>
#include <pthread.h>

static pthread_mutex_t lrand_mutex = PTHREAD_MUTEX_INITIALIZER;

void generate_uuid(char* buf) {
    pthread_mutex_lock(&lrand_mutex);
    long a = lrand48();
    long b = lrand48();
    long c = lrand48();
    long d = lrand48();
    pthread_mutex_unlock(&lrand_mutex);
    sprintf(buf, "frontend=%08lx-%04lx-%04lx-%04lx-%04lx%08lx",
        a,
        b & 0xffff,
        (b & ((long)0x0fff0000) >> 16) | 0x4000,
        (c & 0x0fff) | 0x8000,
        (c & (long)0xffff0000) >> 16,
        d
    );
    return;
}
