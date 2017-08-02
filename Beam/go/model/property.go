package model

import (
	"database/sql"
	"time"

	"github.com/jmoiron/sqlx"
	"github.com/pkg/errors"
)

// PropertyStorage represents property's storage interface.
type PropertyStorage interface {
	// Get returns instance of Property based on the given UUID.
	Get(UUID string) (*Property, bool, error)
}

// Property structure.
type Property struct {
	ID        int
	UUID      string
	Name      string
	AccountID int       `db:"account_id"`
	CreatedAt time.Time `db:"created_at"`
	UpdatedAt time.Time `db:"updated_at"`
}

// PropertyCollection is list of Properties.
type PropertyCollection []*Property

// PropertyDB represents Property's storage MySQL implementation.
type PropertyDB struct {
	MySQL *sqlx.DB
}

// Get returns instance of Property based on the given UUID.
func (pDB *PropertyDB) Get(UUID string) (*Property, bool, error) {
	p := &Property{}
	err := pDB.MySQL.Get(p, "SELECT * FROM properties WHERE uuid = ?", UUID)
	if err != nil {
		if err == sql.ErrNoRows {
			return nil, false, nil
		}
		return nil, false, errors.Wrap(err, "unable to get property from MySQL")
	}
	return p, true, nil
}
