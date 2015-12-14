CFLAGS = -std=gnu89 -Wall -Wextra -pedantic -Wno-long-long -Wformat-security -I/usr/include/postgresql -I/usr/include/pgsql
CLIBS = -lepub -lpq -lpoppler-cpp -lstdc++

all: elibsrv

elibsrv: crc32.o libsql.o libgetconf.o elibsrv.o pdfmeta.o
	gcc crc32.o libsql.o libgetconf.o elibsrv.o pdfmeta.o -o elibsrv $(CLIBS) $(CFLAGS)

elibsrv.o: elibsrv.c
	gcc -c elibsrv.c -o elibsrv.o $(CFLAGS)

libsql.o: libsql.c
	gcc -c libsql.c -o libsql.o $(CFLAGS)

libgetconf.o: libgetconf.c
	gcc -c libgetconf.c -o libgetconf.o $(CFLAGS)

pdfmeta.o: pdfmeta.cpp
	g++ -c pdfmeta.cpp -lpoppler-cpp -o pdfmeta.o -I/usr/include/poppler -I/usr/include/poppler/cpp

crc32.o: crc32.c
	gcc -c crc32.c -o crc32.o $(CFLAGS)

clean:
	rm -f *.o elibsrv
