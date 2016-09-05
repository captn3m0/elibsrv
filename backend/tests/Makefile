CFLAGS = -std=gnu89 -Wall -Wextra -pedantic -Wno-long-long -Wformat-security
CLIBS = -lepub -lsqlite3 -lpoppler-cpp -lstdc++

all: testdb testepub

testdb: testdb.o ../libsql.o
	gcc testdb.o ../libsql.o -o testdb $(CLIBS) $(CFLAGS)

testepub: testepub.o ../meta_common.o ../meta_epub.o
	gcc testepub.o ../meta_common.o ../meta_epub.o -o testepub $(CLIBS) $(CFLAGS)

clean:
	rm -f *.o testdb testepub
