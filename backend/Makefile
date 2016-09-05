CFLAGS = -std=gnu89 -Wall -Wextra -pedantic -Wno-long-long -Wformat-security
CLIBS = -lepub -lsqlite3 -lpoppler-cpp -lstdc++

all: elibsrv

elibsrv: crc32.o libsql.o libgetconf.o elibsrv.o meta_common.o meta_epub.o meta_pdf.o
	gcc crc32.o libsql.o libgetconf.o elibsrv.o meta_common.o meta_epub.o meta_pdf.o -o elibsrv $(CLIBS) $(CFLAGS)

elibsrv.o: elibsrv.c
	gcc -c elibsrv.c -o elibsrv.o $(CFLAGS)

libsql.o: libsql.c
	gcc -c libsql.c -o libsql.o $(CFLAGS)

libgetconf.o: libgetconf.c
	gcc -c libgetconf.c -o libgetconf.o $(CFLAGS)

meta_pdf.o: meta_pdf.cpp
	g++ -c meta_pdf.cpp -lpoppler-cpp -o meta_pdf.o -I/usr/include/poppler -I/usr/include/poppler/cpp

crc32.o: crc32.c
	gcc -c crc32.c -o crc32.o $(CFLAGS)

clean:
	rm -f *.o elibsrv
