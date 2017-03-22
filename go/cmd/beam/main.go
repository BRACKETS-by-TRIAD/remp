//go:generate goagen bootstrap -d gitlab.com/remp/remp/go/cmd/beam/design

package main

import (
	"context"
	"log"
	"net/http"
	"time"

	"github.com/Shopify/sarama"
	"github.com/goadesign/goa"
	"github.com/goadesign/goa/middleware"
	"gitlab.com/remp/remp/go/cmd/beam/app"
	"gitlab.com/remp/remp/go/cmd/beam/controller"
)

const (
	brokerAddr = "localhost:9092"
)

func main() {
	service := goa.New("beam")

	service.Use(middleware.RequestID())
	service.Use(middleware.LogRequest(true))
	service.Use(middleware.ErrorHandler(service, true))
	service.Use(middleware.Recover())

	eventProducer, err := newProducer([]string{brokerAddr})
	if err != nil {
		log.Fatalln(err)
	}
	defer eventProducer.Close()

	app.MountSwaggerController(service, service.NewController("swagger"))
	app.MountTrackController(service, controller.NewTrackController(
		service,
		eventProducer,
	))

	srv := &http.Server{
		Addr:    ":8080",
		Handler: service.Mux,
	}

	if err := srv.ListenAndServe(); err != nil {
		service.LogError("startup", "err", err)
	}

	ctx, cancel := context.WithTimeout(context.Background(), 5*time.Second)
	defer cancel()
	srv.Shutdown(ctx)

}

func newProducer(brokerList []string) (sarama.AsyncProducer, error) {

	config := sarama.NewConfig()
	config.Producer.RequiredAcks = sarama.WaitForLocal       // Only wait for the leader to ack
	config.Producer.Compression = sarama.CompressionSnappy   // Compress messages
	config.Producer.Flush.Frequency = 500 * time.Millisecond // Flush batches every 500ms

	producer, err := sarama.NewAsyncProducer(brokerList, config)
	if err != nil {
		return nil, err
	}

	// We will just log to STDOUT if we're not able to produce messages.
	// Note: messages will only be returned here after all retry attempts are exhausted.
	go func() {
		for err := range producer.Errors() {
			log.Println("Failed to write access log entry:", err)
		}
	}()

	return producer, nil
}
